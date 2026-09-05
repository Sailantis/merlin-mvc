<?php

namespace Azera\Orm\Cast;

/**
 * 'json' cast: PHP arrays/objects <-> JSON text in a TEXT/JSON column.
 *
 * Fixes the otherwise-broken array column path: without the cast,
 * extractData() hands the raw PHP array to PDO (which stringifies to
 * "Array") and hydration assigns the JSON text string onto the array-typed
 * property (TypeError).
 *
 * Contract:
 * - encode: array|\stdClass -> json_encode (throw on failure); scalars
 *   and null pass through untouched (hand-written JSON strings, ints...)
 *   so the cast is safe for partially-typed data.
 * - decode: JSON text -> array (assoc); scalars pass through. Invalid
 *   JSON THROWS — fail loud, it means the column was edited outside the
 *   ORM and silently coercing would hide the corruption.
 * - null-transparent both directions.
 *
 * On mongo documents this cast is a no-op in practice: the metadata type
 * is still 'json', but EntityManager passes values raw to MongoStore and
 * BSON owns the encoding — node->data already holds arrays there.
 *
 * Snapshot contract: the property holds the DECODED array, node->data
 * holds the RAW JSON string — diff() compares the stable string form.
 * Reordering a JSON list reorders the encoded string and therefore counts
 * as a change (PHP `===` on list-likes is order-sensitive): accepted.
 */
final class JsonCast implements Cast
{
    public function encode(mixed $value): mixed
    {
        if ($value === null || \is_scalar($value)) {
            return $value;
        }

        $json = \json_encode($value);

        if ($json === false) {
            throw new \RuntimeException(
                'Cannot encode value as JSON for storage: ' .
                \json_last_error_msg()
            );
        }

        return $json;
    }

    public function decode(mixed $value): mixed
    {
        if ($value === null || !\is_string($value)) {
            return $value;
        }

        $decoded = \json_decode($value, true);

        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Stored JSON is NOT valid: ' .
                \json_last_error_msg()
            );
        }

        return $decoded;
    }
}