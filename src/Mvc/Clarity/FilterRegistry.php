<?php
namespace Merlin\Mvc\Clarity;

/**
 * Registry of named filter callables for the Clarity template engine.
 *
 * Built-in filters are registered in the constructor. User code may add
 * additional filters via {@see addFilter()}. Each filter receives the
 * value as its first argument and any extra pipeline arguments after it.
 *
 * Built-in filters
 * ----------------
 * - escape / e   : htmlspecialchars (UTF-8, ENT_QUOTES)
 * - raw          : identity – disables auto-escaping
 * - trim         : trims whitespace
 * - upper        : strtoupper
 * - lower        : strtolower
 * - length       : strlen for strings, count for arrays
 * - number($dec) : number_format with $dec decimal places (default 2)
 * - date($fmt)   : date() formatting (default 'Y-m-d'); accepts int timestamp
 *                  or DateTimeInterface
 * - json         : json_encode
 */
class FilterRegistry
{
    /** @var array<string, callable> */
    private array $filters = [];

    public function __construct()
    {
        $this->registerBuiltins();
    }

    /**
     * Register a user-defined filter.
     *
     * @param string   $name Filter name used in templates (e.g. 'currency').
     * @param callable $fn   Callable receiving ($value, ...$args).
     * @return static
     */
    public function add(string $name, callable $fn): static
    {
        $this->filters[$name] = $fn;
        return $this;
    }

    /**
     * Check whether a named filter is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->filters[$name]);
    }

    /**
     * Get all registered filters as a name → callable map.
     *
     * @return array<string, callable>
     */
    public function all(): array
    {
        return $this->filters;
    }

    // -------------------------------------------------------------------------

    private function registerBuiltins(): void
    {
        $this->filters['escape'] = static fn(mixed $v): string =>
            htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        // Alias
        $this->filters['e'] = $this->filters['escape'];

        // raw: identity — suppresses auto-escaping
        $this->filters['raw'] = static fn(mixed $v): mixed => $v;

        $this->filters['trim'] = static fn(mixed $v): string => trim((string) $v);

        $this->filters['upper'] = static fn(mixed $v): string => strtoupper((string) $v);

        $this->filters['lower'] = static fn(mixed $v): string => strtolower((string) $v);

        $this->filters['length'] = static function (mixed $v): int {
            if (is_array($v) || $v instanceof \Countable) {
                return count($v);
            }
            return strlen((string) $v);
        };

        $this->filters['number'] = static fn(mixed $v, int $decimals = 2): string =>
            number_format((float) $v, $decimals);

        $this->filters['date'] = static function (mixed $v, string $format = 'Y-m-d'): string {
            if ($v instanceof \DateTimeInterface) {
                return $v->format($format);
            }
            return date($format, is_int($v) ? $v : (int) $v);
        };

        $this->filters['json'] = static fn(mixed $v): string => (string) json_encode($v);
    }
}
