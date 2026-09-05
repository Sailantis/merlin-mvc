<?php

namespace Azera\Orm\Cast;

use Azera\Orm\Cast\Cast;

/**
 * Scalar float cast — see IntCast for the shared rationale.
 *
 * @internal registry detail — registered as 'float'
 */
final class FloatCast implements Cast
{
    public function encode(mixed $value): mixed
    {
        return $value;
    }

    public function decode(mixed $value): mixed
    {
        return $value === null ? null : (float) $value;
    }
}