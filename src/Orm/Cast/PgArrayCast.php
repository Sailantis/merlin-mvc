<?php

namespace Azera\Orm\Cast;

/**
 * 'pgarray' cast: PHP 1-D scalar array <-> PostgreSQL native array
 * literal (the string form PDO returns for array columns: `{1,2,3}`,
 * `{"a b","c\"d",NULL}`).
 *
 * This is NOT JSON — json_decode('{1,2,3}') fails. A PHP array property
 * backed by a NATIVE pg array column must be declared
 * `#[Column(type: 'pgarray')]` (the inference default is 'json', the
 * portable form that works on mysql/sqlite/mongo).
 *
 * Scope: arrays of scalars (int, float, bool, string, null), 1-D and
 * nested (2-D+) — encode() recurses into sub-arrays, decode() parses the
 * full literal grammar back to nested PHP arrays. Dimension regularity
 * (rectangular shape, matching depths) is validated by the SERVER at
 * write time: a ragged literal like `{{1},{2,3}}` binds fine and pg
 * rejects the INSERT — fail loud at flush, never silently wrong.
 * Nesting is capped at 6 levels (pg's own dimension limit) — deeper
 * input throws app-side instead of exhausting the stack.
 *
 * Write direction: the literal binds as a plain string parameter — pg
 * infers the parameter type from the target column, so `'{"a b"}'`
 * against a text[] column just works.
 */
final class PgArrayCast implements Cast
{
    /** pg's own hard limit on array dimensions. */
    private const MAX_DIMENSIONS = 6;

    public function encode(mixed $value): mixed
    {
        if ($value === null || \is_scalar($value)) {
            return $value;
        }

        if (!\is_array($value)) {
            throw new \RuntimeException(
                'pgarray column expects array|null, got ' . \get_debug_type($value)
            );
        }

        $elements = [];
        foreach ($value as $el) {
            $elements[] = $this->literal($el, 1);
        }
        return '{' . \implode(',', $elements) . '}';
    }

    public function decode(mixed $value): mixed
    {
        if ($value === null || !\is_string($value)) {
            return $value;
        }

        return $this->parse($value);
    }

    /* ------------------------------------------------------- encoding */

    /**
     * One element -> pg literal text (with quotes/escapes when needed).
     */
    private function literal(mixed $el, int $depth): string
    {
        if ($el === null) {
            return 'NULL';
        }

        if (\is_array($el)) {
            if ($depth >= self::MAX_DIMENSIONS) {
                throw new \RuntimeException(
                    'pg array nesting deeper than '
                    . self::MAX_DIMENSIONS . ' dimensions'
                );
            }

            $elements = [];
            foreach ($el as $sub) {
                $elements[] = $this->literal($sub, $depth + 1);
            }
            return '{' . \implode(',', $elements) . '}';
        }

        if (\is_bool($el)) {
            return $el ? 't' : 'f';
        }

        if (\is_int($el) || \is_float($el)) {
            return (string) $el;
        }

        $s = (string) $el;

        if ($s === '') {
            return '""';
        }

        // Quote when the element could be misread (delimiters, quotes,
        // escapes, whitespace, NULL ambiguity, braces).
        if (\preg_match('/[{},\\\\\s]|^null$/i', $s) || \str_contains($s, '"')) {
            return '"' . \str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
        }

        return $s;
    }

    /* ------------------------------------------------------- parsing */

    /**
     * Parse a pg array literal (recursive for nested forms; 1-D columns
     * never produce nesting in practice).
     *
     * @return list<mixed>
     */
    private function parse(string $literal): array
    {
        $literal = \trim($literal);

        if ($literal === '' || $literal === '{}') {
            return [];
        }

        if ($literal[0] !== '{') {
            throw new \RuntimeException(
                "Stored pg array literal is malformed: '{$literal}'"
            );
        }

        $pos = 1;
        [$result] = $this->parseLevel($literal, $pos, 1);

        return $result;
    }

    /**
     * Coerce a bare-text element to its native PHP scalar (pg sends no
     * type info per element; ints/floats/bools round-trip natively,
     * everything else stays a string).
     */
    private function scalar(string $raw): mixed
    {
        if ($raw === '') {
            return '';
        }

        if (\strlen($raw) === 1) {
            if ($raw === 't' || $raw === 'T') {
                return true;
            }
            if ($raw === 'f' || $raw === 'F') {
                return false;
            }
            if (\ctype_digit($raw)) {
                return (int) $raw;
            }
            return $raw;
        }

        if (\ctype_digit(ltrim($raw, '-'))) {
            return (int) $raw;
        }

        if (\is_numeric($raw)) {
            return (float) $raw;
        }

        if (\strcasecmp($raw, 'null') === 0) {
            return null;
        }

        return $raw;
    }

    /**
     * Parse elements until the closing brace of the current level.
     *
     * @return array{0: list<mixed>, 1: int}
     */
    private function parseLevel(string $s, int $pos, int $depth): array
    {
        if ($depth > self::MAX_DIMENSIONS) {
            throw new \RuntimeException(
                'pg array literal nests deeper than '
                . self::MAX_DIMENSIONS . ' dimensions'
            );
        }

        $out = [];
        $len = \strlen($s);

        while ($pos < $len) {
            $char = $s[$pos];

            // pg's lexer ignores whitespace between tokens ({1, 2}, { "x" }).
            // Whitespace INSIDE quoted/bare elements is content — the
            // branches below consume their own characters.
            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r") {
                $pos++;
                continue;
            }

            if ($char === '}') {
                return [$out, $pos + 1];
            }

            if ($char === ',') {
                $pos++;
                continue;
            }

            if ($char === '{') {
                [$sub, $pos] = $this->parseLevel($s, $pos + 1, $depth + 1);
                $out[] = $sub;
                continue;
            }

            if ($char === '"') {
                // Quoted element: backslash escapes inside. Quoted text is
                // always a STRING (quoting is how pg preserves type) —
                // '1' stays '1', never coerced to int.
                $buf = '';
                $pos++;
                $closed = false;
                while ($pos < $len) {
                    if ($s[$pos] === '\\') {
                        if ($pos + 1 >= $len) {
                            break; // dangling escape: malformed
                        }
                        $buf .= $s[$pos + 1];
                        $pos += 2;
                        continue;
                    }
                    if ($s[$pos] === '"') {
                        $closed = true;
                        $pos++;
                        break;
                    }
                    $buf .= $s[$pos];
                    $pos++;
                }
                if (!$closed) {
                    throw new \RuntimeException(
                        "Stored pg array literal is malformed (unterminated quote): '{$s}'"
                    );
                }
                $out[] = $buf;
                continue;
            }

            // Bare element: up to delimiter OR whitespace (pg-true: unquoted
            // elements cannot contain spaces — quoting is how pg preserves
            // them; `\ ` keeps an escaped space as content). Coerced to
            // native scalars; the lexer skips separator whitespace.
            $buf = '';
            while (
                $pos < $len
                && !\in_array($s[$pos], [',', '}', ' ', "\t", "\n", "\r"])
            ) {
                if ($s[$pos] === '\\' && $pos + 1 < $len) {
                    $pos++;
                }
                $buf .= $s[$pos];
                $pos++;
            }
            $out[] = $this->scalar($buf);
        }

        return [$out, $pos];
    }
}