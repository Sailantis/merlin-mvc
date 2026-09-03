<?php

namespace Azera\Orm;

use Azera\Orm\Attribute\BelongsTo;
use Azera\Orm\Attribute\Column;
use Azera\Orm\Attribute\Document;
use Azera\Orm\Attribute\HasMany;
use Azera\Orm\Attribute\HasOne;
use Azera\Core\ModelMapping;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Compiles class attributes into a plain metadata array, ONCE per class.
 *
 * Compiled shape (all values JSON-serializable — required for the L2 cache):
 *
 * [
 *   'class'      => class-string,
 *   'source'     => string,              // static fallback; source() may override per request
 *   'store'      => 'sql'|'mongo',
 *   'collection' => ?string,             // mongo only
 *   'columns'    => [name => ['name' =>.., 'type' =>.., 'nullable' =>.., 'transient' =>..]],
 *   'pk'         => string[],            // idFields() is the dynamic authority; 'columns' marked pk => true
 *   'relations'  => [name => ['type'=>.., 'target'=>.., 'foreignKey'=>.., 'ownerKey'=>.., 'strategy' => 'join'|'second_query']],
 * ]
 *
 * Caching (two tiers):
 * - L1: per-process static array (survives across RoadRunner requests).
 * - L2: APCu when available, else a var/cache file — covers PHP-FPM where
 *   L1 dies with the request. Key includes a version salt so stale entries
 *   from older compiler versions are ignored.
 *
 * Dynamic per-request overrides (source(), schema(), roles, tenancy) are
 * NOT part of this array — they remain instance-method overrides, exactly
 * as decided.
 */
final class Metadata
{
    /** Bump when compiler output changes shape — invalidates L2 entries. */
    private const VERSION = 'v2';

    /** @var array<class-string, array> */
    private static array $l1 = [];

    private static ?bool $apcuEnabled = null;

    /**
     * Compile (or fetch from cache) metadata for a class.
     *
     * @param class-string $class
     */
    public static function for(string $class): array
    {
        $class = ltrim($class, '\\');

        if (isset(self::$l1[$class])) {
            return self::$l1[$class];
        }

        $meta = self::fromL2($class) ?? self::compile($class);

        return self::$l1[$class] = $meta;
    }

    /**
     * Forget all cached metadata (tests).
     */
    public static function clear(): void
    {
        self::$l1 = [];
        if (self::apcuEnabled()) {
            apcu_clear_cache();
        }
    }

    /* ------------------------------------------------------------- l2 */

    private static function cacheKey(string $class): string
    {
        return 'azera_orm_meta_' . self::VERSION . '_' . str_replace('\\', '__', $class);
    }

    private static function fromL2(string $class): ?array
    {
        if (!self::apcuEnabled()) {
            return null;
        }

        $success = false;
        /** @var mixed $meta */
        $meta = apcu_fetch(self::cacheKey($class), $success);

        return $success && \is_array($meta) ? $meta : null;
    }

    private static function toL2(string $class, array $meta): void
    {
        if (self::apcuEnabled()) {
            apcu_add(self::cacheKey($class), $meta);
        }
    }

    private static function apcuEnabled(): bool
    {
        if (self::$apcuEnabled === null) {
            self::$apcuEnabled = \extension_loaded('apcu')
                && (bool) \ini_get('apc.enabled')
                && \ini_get('apc.enable_cli') !== '0';
        }

        return self::$apcuEnabled;
    }

    /* -------------------------------------------------------- compile */

    /**
     * @param class-string $class
     * @return array
     */
    private static function compile(string $class): array
    {
        $reflect = new ReflectionClass($class);
        $short   = $reflect->getShortName();

        $meta = [
            'class' => $class,
            // The model's own source() is the authority when it overrides
            // the convention (e.g. an app model mapping to a custom table).
            // Falls back to the convention for plain classes (fixtures).
            'source'     => self::resolveSource($reflect),
            'store'      => 'sql',
            'collection' => null,
            'columns'    => [],
            'relations'  => [],
        ];

        $docAttrs = $reflect->getAttributes(Document::class, \ReflectionAttribute::IS_INSTANCEOF);
        if ($docAttrs !== []) {
            $doc = $docAttrs[0]->newInstance();
            $meta['store']      = 'mongo';
            $meta['collection'] = $doc->collection;
        }

        foreach ($reflect->getProperties() as $prop) {
            // Internal properties are never persisted.
            if (str_starts_with($prop->name, '__')) {
                continue;
            }

            $relation = self::relationAttribute($prop);
            if ($relation !== null) {
                $meta['relations'][$prop->name] = self::compileRelation($prop->name, $relation, $short);
                continue;
            }

            $column = self::columnAttribute($prop);
            if ($column !== null && $column->transient) {
                continue;
            }

            $meta['columns'][$prop->name] = [
                'name'     => $column?->name ?? $prop->name,
                'type'     => $column?->type ?? self::inferType($prop),
                'nullable' => $column?->nullable ?? false,
                'pk'       => $column?->name === null
                    ? ($prop->name === 'id' || str_ends_with($prop->name, '_id'))
                    : false,
            ];
        }

        self::toL2($class, $meta);

        return $meta;
    }

    /**
     * Resolve the effective source (table) name for a class.
     *
     * If the class is an Azera Model and OVERRIDES source() (i.e. its
     * value differs from the naming convention), that value wins — the
     * Store seam must target the same table the Query builder uses.
     * Plain classes (ORM test fixtures) use the convention.
     */
    private static function resolveSource(\ReflectionClass $reflect): string
    {
        $convention = ModelMapping::convertModelToSource($reflect->getShortName());

        if (!$reflect->isSubclassOf(\Azera\Core\Model::class)) {
            return $convention;
        }

        try {
            $instance = $reflect->newInstanceWithoutConstructor();
            $override = $instance->source();
        } catch (\Throwable) {
            return $convention;
        }

        return ($override !== '' && $override !== $convention) ? $override : $convention;
    }

    private static function relationAttribute(ReflectionProperty $prop): ?object
    {
        foreach ([BelongsTo::class, HasOne::class, HasMany::class] as $attr) {
            $found = $prop->getAttributes($attr, \ReflectionAttribute::IS_INSTANCEOF);
            if ($found !== []) {
                return $found[0]->newInstance();
            }
        }

        return null;
    }

    private static function columnAttribute(ReflectionProperty $prop): ?Column
    {
        $found = $prop->getAttributes(Column::class, \ReflectionAttribute::IS_INSTANCEOF);

        return $found === [] ? null : $found[0]->newInstance();
    }

    /**
     * @return array<string, mixed>
     */
    private static function compileRelation(string $name, object $relation, string $short): array
    {
        if ($relation instanceof BelongsTo) {
            return [
                'type'       => 'belongsTo',
                'target'     => $relation->target,
                'foreignKey' => $relation->foreignKey ?? ($name . '_id'),
                'ownerKey'   => $relation->ownerKey ?? 'id',
                'strategy'   => 'join',
            ];
        }

        // HasOne / HasMany: the foreign key lives on the TARGET table and
        // references THIS model.
        return [
            'type'       => $relation instanceof HasOne ? 'hasOne' : 'hasMany',
            'target'     => $relation->target,
            'foreignKey' => $relation->foreignKey
                ?? ModelMapping::convertModelToSource($short) . '_id',
            'ownerKey' => $relation->ownerKey ?? 'id',
            'strategy' => $relation instanceof HasOne ? 'join' : 'second_query',
        ];
    }

    private static function inferType(ReflectionProperty $prop): string
    {
        $type = $prop->getType();

        if ($type instanceof ReflectionNamedType) {
            return match ($type->getName()) {
                'int'                                                => 'int',
                'float'                                              => 'float',
                'bool'                                               => 'bool',
                'DateTimeInterface', 'DateTime', 'DateTimeImmutable' => 'datetime',
                'array'                                              => 'json',
                default                                              => 'string'
            };
        }

        return 'string';
    }
}