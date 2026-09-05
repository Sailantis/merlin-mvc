<?php

namespace Azera\Orm\Cast;

/**
 * Registry mapping metadata column types to {@see Cast} transformations.
 *
 * The cast key is the COLUMN type declared (or inferred) in metadata —
 * `#[Column(type: 'json')]` or the property-type inference (array ->
 * 'json', int -> 'int', ...). Inference only guesses the PORTABLE default:
 * an `array` property on PostgreSQL backed by a native array column must
 * be declared explicitly as `#[Column(type: 'pgarray')]`.
 *
 * Registered built-ins (registered in {@see Casts::boot()}):
 *
 *   'int'      decode coerces strings -> int (both property AND snapshot)
 *   'float'    decode coerces strings -> float (both directions as int)
 *   'bool'     decode coerces '1'/'0'/'t'/'f'/... -> bool
 *   'json'     encode json_encode, decode json_decode(..., true)
 *   'pgarray'  PostgreSQL native array literal <-> 1-D scalar PHP array
 *
 * Semantics:
 *
 * - Registered casts apply on BOTH read and write paths; scalar casts
 *   exist because stringifying drivers (pdo_mysql with emulated prepares,
 *   pdo_pgsql) return numerics as strings — without them the typed
 *   property would coerce `int(5)` while the heap snapshot kept `"5"`,
 *   making diff() schedule a redundant UPDATE for every unchanged numeric
 *   column on the first persist after hydration.
 *
 * - Applications can register additional types (encrypted columns, enums,
 *   money, ...): `Casts::register('encrypted', new EncryptedCast())`.
 *   Registration before the first Metadata::for() call of the class, or
 *   Metadata::clear() afterwards — FastHydrator compiles the decode plan
 *   per class once.
 */
final class Casts
{
    /** @var array<string, Cast> */
    private static array $casts = [];

    /** @var bool built-ins registered? */
    private static bool $booted = false;

    /**
     * Register (or replace) a cast for a column type.
     */
    public static function register(string $type, Cast $cast): void
    {
        self::boot();

        self::$casts[$type] = $cast;
    }

    /**
     * The cast for a column type, or null when the type has no
     * transformation (values pass through raw in both directions).
     */
    public static function for(string $type): ?Cast
    {
        self::boot();

        return self::$casts[$type] ?? null;
    }

    /**
     * Registered type names (tests).
     *
     * @return list<string>
     */
    public static function types(): array
    {
        self::boot();

        return array_keys(self::$casts);
    }

    /**
     * Drop the registry (tests) — built-ins re-register on next use.
     */
    public static function clear(): void
    {
        self::$casts = [];
        self::$booted = false;
    }

    /**
     * Register the built-in casts once.
     */
    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        self::$casts['int'] = new IntCast();
        self::$casts['float'] = new FloatCast();
        self::$casts['bool'] = new BoolCast();
        self::$casts['json'] = new JsonCast();
        self::$casts['pgarray'] = new PgArrayCast();
    }
}