<?php

declare(strict_types=1);

namespace Azera\Db;

/**
 * ModelMapping is a configuration class that maps logical model names to
 * database table sources.
 * It provides methods to define, retrieve, and manipulate model mappings,
 * including automatic conversion of model names to table names (snake_case)
 * and optional pluralization.
 */
class ModelMapping
{
    /**
     * @var array
     */
    protected array $mapping;

    /**
     * Create ModelMapping from array config
     * @param array $mapping
     * @return static
     */
    public static function fromArray(array $mapping): static
    {
        $instance = new self();
        foreach ($mapping ?? [] as $name => $config) {
            if (!\is_string($name)) {
                throw new \InvalidArgumentException('Model name must be a string');
            }
            if (\is_string($config)) {
                // "User" => "users"
                $config = [
                    'source' => $config,
                    'schema' => null,
                    'connection' => null,
                    'read' => null,
                    'write' => null,
                ];
            } elseif (\is_bool($config) && $config === true) {
                // "User" => true  (auto-generate source name)
                $config = [
                    'source' => static::convertModelToSource($name),
                    'schema' => null,
                    'connection' => null,
                    'read' => null,
                    'write' => null,
                ];
            } elseif (!\is_array($config)) {
                throw new \InvalidArgumentException("Model config must be a string, an array, or true for model '$name'");
            } elseif (empty($config['source'])) {
                throw new \InvalidArgumentException("Model source cannot be empty for model '$name'");
            } elseif (!\is_string($config['source'])) {
                throw new \InvalidArgumentException("Model source must be a string for model '$name'");
            } else {
                // Normalize optional keys
                if (!isset($config['schema'])) {
                    $config['schema'] = null;
                } elseif (!\is_string($config['schema'])) {
                    throw new \InvalidArgumentException("Model schema must be a string for model '$name'");
                }
                if (!isset($config['connection'])) {
                    $config['connection'] = null;
                } elseif (!\is_string($config['connection'])) {
                    throw new \InvalidArgumentException("Model connection must be a string for model '$name'");
                }
                if (!isset($config['read'])) {
                    $config['read'] = null;
                } elseif (!\is_string($config['read'])) {
                    throw new \InvalidArgumentException("Model read connection must be a string for model '$name'");
                }
                if (!isset($config['write'])) {
                    $config['write'] = null;
                } elseif (!\is_string($config['write'])) {
                    throw new \InvalidArgumentException("Model write connection must be a string for model '$name'");
                }
            }
            $instance->mapping[$name] = $config;
        }
        return $instance;
    }

    /** Whether to pluralize table names automatically when no source is given */
    protected static bool $pluralizeTableNames = false;

    /**
     * Enable or disable automatic table name pluralization.
     * When enabled, model names are converted to plural snake_case table names
     * (e.g. User → users, AdminUser → admin_users, Person → people).
     */
    public static function usePluralTableNames(bool $enable): void
    {
        static::$pluralizeTableNames = $enable;
    }

    /**
     * Returns whether automatic table name pluralization is enabled.
     */
    public static function usingPluralTableNames(): bool
    {
        return static::$pluralizeTableNames;
    }

    /**
     * Add model mapping
     * @param string $name
     * @param string|null $source
     * @param string|null $schema
     * @param string|null $connection Connection role for both read and write
     * @param string|null $read Connection role for read queries (overrides $connection)
     * @param string|null $write Connection role for write queries (overrides $connection)
     * @return $this
     */
    public function add(string $name, ?string $source = null, ?string $schema = null, ?string $connection = null, ?string $read = null, ?string $write = null): static
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Model name cannot be empty');
        }
        if (empty($source)) {
            $source = static::convertModelToSource($name);
        }
        $this->mapping[$name] = [
            'source' => $source,
            'schema' => $schema,
            'connection' => $connection,
            'read' => $read,
            'write' => $write,
        ];
        return $this;
    }

    /**
     * Get model mapping by name
     * @param string $name
     * @return array|null
     */
    public function get(string $name): ?array
    {
        return $this->mapping[$name] ?? null;
    }

    /**
     * Get all model mappings as an array
     * @return array
     */
    public function toArray(): array
    {
        return $this->mapping;
    }

    /**
     * Convert a model name to a default source name (table name).
     * By default, converts PascalCase or camelCase to snake_case (e.g. AdminUser → admin_user).
     * When pluralization is enabled, the last word segment is pluralized (e.g. AdminUser → admin_users).
     *
     * @param string $modelName The model class name to convert.
     * @return string The converted source name (table name).
     */
    public static function convertModelToSource(string $modelName): string
    {
        // AdminUserFlags -> admin_user_flags
        $source = static::toSnakeCase($modelName);
        if (static::$pluralizeTableNames) {
            // Pluralize only the last word segment (e.g. admin_user -> admin_users)
            $pos = strrpos($source, '_');
            if ($pos !== false) {
                $prefix = substr($source, 0, $pos);
                $suffix = substr($source, $pos + 1);
                $suffix = static::pluralize($suffix);
                $source = $prefix . '_' . $suffix;
            } else {
                // single word model name (e.g. User -> users)
                // -> pluralize the whole name
                $source = static::pluralize($source);
            }
        }
        return $source;
    }

    /**
     * Convert a string to snake_case.
     * Handles various input formats, including camelCase, PascalCase, kebab-case, and space-separated words.
     * Consecutive uppercase letters are treated as acronyms (e.g., XMLParser → xml_parser).
     * Multiple separators are unified into a single underscore, and duplicate underscores are avoided.
     *
     * @param string $name The input string to convert.
     * @return string The converted snake_case string.
     */
    public static function toSnakeCase(string $name): string
    {
        // unify separators
        $name = str_replace(['-', '.', ' '], '_', $name);

        $result = '';
        $length = strlen($name);
        $isUnderscore = false; // ensure initialization

        for ($i = 0; $i < $length; $i++) {
            $char = $name[$i];
            $isUpper = ctype_upper($char);

            if ($isUpper) {
                // previous char exists and is:
                // - lowercase
                // - digit
                // - uppercase followed by lowercase (XMLParser → xml_parser)
                if (
                    $i > 0 &&
                                    (ctype_lower($name[$i - 1]) ||
                                        ctype_digit($name[$i - 1]) ||
                                        ($i + 1 < $length && ctype_lower($name[$i + 1])))
                ) {
                    if (!$isUnderscore) {
                        $result .= '_';
                        $isUnderscore = true;
                    }
                }

                $char = strtolower($char);

            } elseif ($char === '_') {
                if ($isUnderscore) {
                    continue; // skip duplicate underscores
                }
                $isUnderscore = true;
                $result .= '_';
                continue;
            }

            $isUnderscore = false;
            $result .= $char;
        }

        return $result;
    }

    /** Irregular singular → plural mappings */
    protected static array $irregulars = [
        'person' => 'people',
        'mouse' => 'mice',
        'child' => 'children',
        'man' => 'men',
        'woman' => 'women',
        'tooth' => 'teeth',
        'foot' => 'feet',
        'goose' => 'geese',
        'ox' => 'oxen',
        'louse' => 'lice',
        'cactus' => 'cacti',
        'focus' => 'foci',
        'analysis' => 'analyses',
        'index' => 'indices',
        'appendix' => 'appendices',

        // additional Latin/Greek irregulars
        'datum' => 'data',
        'bacterium' => 'bacteria',
        'criterion' => 'criteria',
        'phenomenon' => 'phenomena',
        'medium' => 'media',
        'radius' => 'radii',
        'matrix' => 'matrices',
        'vertex' => 'vertices',
        'axis' => 'axes',
        'fungus' => 'fungi',
        'syllabus' => 'syllabi',

        // invariant plurals (same singular and plural)
        'sheep' => 'sheep',
        'deer' => 'deer',
        'fish' => 'fish',
        'species' => 'species',
        'series' => 'series',
        'aircraft' => 'aircraft',
        'moose' => 'moose',
        'salmon' => 'salmon',
        'bison' => 'bison',
        'offspring' => 'offspring',

        // other common irregulars
        'thesis' => 'theses',
        'crisis' => 'crises',
        'diagnosis' => 'diagnoses',
        'parenthesis' => 'parentheses',
        'synthesis' => 'syntheses',
    ];

    /**
     * Return the plural form of a word (always lowercase).
     * Returns the word unchanged if it appears to be already plural.
     * Irregular plurals are applied first; regular suffix rules are used otherwise.
     *
     * @param string $word Singular word.
     * @return string Pluralized lowercase word.
     */
    protected static function pluralize(string $word): string
    {
        $word = mb_strtolower($word);

        // 0) already plural – return unchanged
        if (static::isAlreadyPlural($word)) {
            return $word;
        }

        // 1) irregular plurals
        if (isset(self::$irregulars[$word])) {
            return self::$irregulars[$word];
        }

        // 2) words ending with y preceded by a consonant -> replace y with ies
        if (preg_match('/[b-df-hj-np-tv-z]y$/', $word)) {
            return preg_replace('/y$/', 'ies', $word);
        }

        // 3) words ending with s, sh, ch, x, z -> add es
        if (preg_match('/(s|sh|ch|x|z)$/', $word)) {
            return $word . 'es';
        }

        // 4) words ending with f or fe -> replace with ves
        if (preg_match('/(fe|f)$/', $word)) {
            return preg_replace('/(fe|f)$/', 'ves', $word);
        }

        // 5) default: append s
        return $word . 's';
    }

    /**
     * Returns true when a word appears to already be plural, preventing double pluralization.
     *
     * Detected cases:
     * - Known irregular plural values (people, children, …)
     * - Words ending in ies / ves  (categories, knives)
     * - Words ending in sibilant+es suffixes (classes, boxes, churches, …)
     * - Words ending in a non-sibilant consonant followed by s (flags, items, users)
     *
     * Limitation: regular plurals ending in a vowel + s (e.g. "roles") are not detected.
     */
    protected static function isAlreadyPlural(string $word): bool
    {
        static $irregularsFlipped = null;
        if ($irregularsFlipped === null) {
            $irregularsFlipped = array_flip(self::$irregulars);
        }

        // known irregular plural values
        if (isset($irregularsFlipped[$word])) {
            return true;
        }

        // common plural suffixes produced by the pluralize rules
        if (preg_match('/(ies|ves|ses|xes|zes|ches|shes)$/', $word)) {
            return true;
        }

        // ends in a non-sibilant consonant + s  →  flags, items, users, cards, …
        if (preg_match('/[bcdfghjklmnpqrtvwyz]s$/', $word)) {
            return true;
        }

        return false;
    }
}