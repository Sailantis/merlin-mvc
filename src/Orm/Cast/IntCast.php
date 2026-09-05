<?php

namespace Azera\Orm\Cast;

/**
 * Scalar casts: decode the strings stringifying drivers return
 * (pdo_mysql emulated prepares, pdo_pgsql) into the declared PHP type.
 *
 * Applied to BOTH the entity property and the heap snapshot during
 * hydration so diff() compares like with like — without the snapshot
 * coercion, the property coerces (weak-mode typed assignment) while the
 * snapshot keeps the raw string, and the first persist of an UNCHANGED
 * entity schedules a redundant UPDATE per numeric column.
 *
 * Encode is pass-through for all three: PHP already binds ints/floats/
 * bools natively and the server coerces on write.
 */

/** @internal registry detail — registered as 'int' */
final class IntCast implements Cast
{
    public function encode(mixed $value): mixed
    {
        return $value;
    }

    public function decode(mixed $value): mixed
    {
        return $value === null ? null : (int) $value;
    }
}