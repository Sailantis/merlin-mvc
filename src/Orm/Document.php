<?php

namespace Azera\Orm;

use Azera\AppContext;
use Azera\Orm\Storage\StoreManager;

/**
 * Base class for MongoDB-backed objects (pairs with #[Document]).
 *
 * Shares {@see Stateful} with SQL models — same snapshot/dirty-diff
 * engine. Persistence goes through the Store seam: metadata store='mongo'
 * selects the MongoStore resolved via storeRole(). save() = $set diff from
 * the snapshot (Mongo echoes the doc back, no RETURNING).
 *
 * No joins v1: with() resolves relations client-side (second queries only);
 * cross-store relations are forbidden by design.
 */
abstract class Document extends Stateful
{
    /**
     * Which StoreManager role resolves the backend for this document.
     * Override for Mongo tenancy (e.g. 'mongo-tenant-a' -> tenant db).
     */
    public function storeRole(): string
    {
        return 'default';
    }

    /**
     * Save via the Mongo store: INSERT when no PK, $set-diff UPDATE when
     * PK present.
     */
    public function save(): bool
    {
        $store = AppContext::instance()->get(StoreManager::class)
            ->getOrDefault($this->storeRole());

        $meta = Metadata::for(static::class);
        $diff = $this->mongoDiff($meta);

        if ($diff === []) {
            return false;
        }

        $pkField = $this->pkField($meta);

        if (($this->{$pkField} ?? null) !== null) {
            $store->updateOne(static::class, $diff, [$pkField => $this->{$pkField}]);
        } else {
            unset($diff[$pkField]);
            $result = $store->insertOne(static::class, $diff);
            if (isset($result['id'])) {
                $this->{$pkField} = $result['id'];
            }
        }

        $this->saveState();
        return true;
    }

    /**
     * Field-name-keyed diff: only changed fields land in $set.
     * Uses Stateful's snapshot diff (string-cast caveat documented in
     * StatefulTest — Mongo $set is per-field, so array values are
     * serialized as JSON before comparison).
     */
    protected function mongoDiff(array $meta = null): array
    {
        $meta ??= Metadata::for(static::class);
        $diff = [];

        foreach ($this->__getChangedValues() as $field => $value) {
            if (!isset($meta['columns'][$field])) {
                continue;
            }
            $diff[$field] = \is_array($value) ? json_encode($value) : $value;
        }

        return $diff;
    }

    private function pkField(array $meta): string
    {
        foreach ($meta['columns'] as $field => $col) {
            if ($col['pk']) {
                return $field;
            }
        }

        return 'id';
    }

    public function delete(): bool
    {
        $store = AppContext::instance()->get(StoreManager::class)
            ->getOrDefault($this->storeRole());

        $meta = Metadata::for(static::class);
        $pk   = $this->pkField($meta);

        if (($this->{$pk} ?? null) === null) {
            throw new \RuntimeException('Cannot delete without PK');
        }

        $store->deleteOne(static::class, [$pk => $this->{$pk}]);
        $this->saveState();
        return true;
    }
}