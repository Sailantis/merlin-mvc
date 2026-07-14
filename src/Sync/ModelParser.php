<?php
namespace Merlin\Sync;

use ReflectionClass;
use RuntimeException;

class ModelParser
{
    public function __construct(
        private string $filePath
    ) {}

    public function parse(): ParsedModel
    {
        $code   = file_get_contents($this->filePath);
        $tokens = token_get_all($code);

        // Build a byte-offset map: $offsets[$i] = start position of token $i in $code
        $offsets = [];
        $pos     = 0;
        foreach ($tokens as $i => $token) {
            $offsets[$i] = $pos;
            $pos += is_array($token) ? strlen($token[1]) : strlen($token);
        }

        $className = $this->resolveClassName($tokens);
        // @phpstan-ignore PhpIncludeInspection
        require_once $this->filePath;

        $ref = new ReflectionClass($className);

        return new ParsedModel(
            filePath: $this->filePath,
            className: $className,
            classComment: $this->extractClassComment($tokens),
            properties: $this->extractProperties($ref, $tokens),
            insertionOffset: $this->findInsertionOffset($tokens, $offsets)
        );
    }

    private function resolveClassName(array $tokens): string
    {
        $namespace = '';
        $class     = '';

        for ($i = 0; $i < count($tokens); $i++) {
            if (is_array($tokens[$i])) {
                if ($tokens[$i][0] === T_NAMESPACE) {
                    $namespace = $this->collectNamespace($tokens, $i);
                }
                if ($tokens[$i][0] === T_CLASS) {
                    $class = $this->collectClassName($tokens, $i);
                }
            }
        }

        return $namespace ? "$namespace\\$class" : $class;
    }

    private function collectNamespace(array $tokens, int $i): string
    {
        $i++; // advance past T_NAMESPACE

        // Skip whitespace
        while (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }

        if (!isset($tokens[$i]) || !is_array($tokens[$i])) {
            return '';
        }

        // PHP 8.0+ emits T_NAME_QUALIFIED ('Foo\Bar') or T_NAME_FULLY_QUALIFIED ('\Foo\Bar')
        // as a single token instead of individual T_STRING + T_NS_SEPARATOR tokens.
        if (
            (defined('T_NAME_QUALIFIED') && $tokens[$i][0] === T_NAME_QUALIFIED) ||
            (defined('T_NAME_FULLY_QUALIFIED') && $tokens[$i][0] === T_NAME_FULLY_QUALIFIED)
        ) {
            return ltrim($tokens[$i][1], '\\');
        }

        // PHP < 8.0: collect T_STRING and T_NS_SEPARATOR tokens
        $ns = '';
        while (
            isset($tokens[$i]) && is_array($tokens[$i]) &&
            ($tokens[$i][0] === T_STRING || $tokens[$i][0] === T_NS_SEPARATOR)
        ) {
            $ns .= $tokens[$i][1];
            $i++;
        }

        return $ns;
    }

    private function collectClassName(array $tokens, int $i): string
    {
        $i++;
        while (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        return $tokens[$i][1];
    }

    private function extractClassComment(array $tokens): ?string
    {
        for ($i = 0; $i < count($tokens); $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_CLASS) {
                for ($j = $i - 1; $j >= 0; $j--) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_DOC_COMMENT) {
                        return $tokens[$j][1];
                    }
                    if (is_array($tokens[$j]) && $tokens[$j][0] !== T_WHITESPACE) {
                        break;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Extract class properties as a name-keyed array.
     *
     * @return array<string, ParsedProperty>
     */
    private function extractProperties(ReflectionClass $ref, array $tokens): array
    {
        $properties = [];
        $n          = count($tokens);

        for ($i = 0; $i < $n; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_VARIABLE) {
                continue;
            }

            $name = substr($tokens[$i][1], 1); // strip '$'

            if (!$ref->hasProperty($name)) {
                continue;
            }

            $propRef = $ref->getProperty($name);

            // Skip static and inherited properties
            if ($propRef->isStatic() || $propRef->getDeclaringClass()->getName() !== $ref->getName()) {
                continue;
            }

            $docComment = $propRef->getDocComment() ?: null;

            $properties[$name] = new ParsedProperty(
                name: $name,
                type: $propRef->getType() ? (string) $propRef->getType() : null,
                docComment: $docComment
            );
        }

        return $properties;
    }

    /**
     * Find the byte offset immediately after the last property semicolon, which is
     * the correct insertion point for new properties. Falls back to after the
     * opening class brace when there are no existing properties.
     */
    private function findInsertionOffset(array $tokens, array $offsets): int
    {
        $lastSemiOffset = null;
        $n              = count($tokens);

        // Scan for the last ';' token that terminates a property declaration.
        // We track whether we're inside a method (brace depth > 1 after class opening).
        $braceDepth  = 0;
        $classOpened = false;

        for ($i = 0; $i < $n; $i++) {
            $tok = $tokens[$i];

            if ($tok === '{') {
                $braceDepth++;
                if ($braceDepth === 1) {
                    $classOpened = true;
                }
                continue;
            }

            if ($tok === '}') {
                $braceDepth--;
                continue;
            }

            // A ';' at class-body level (depth 1) ends a property or abstract method
            if ($tok === ';' && $braceDepth === 1 && $classOpened) {
                // Confirm it's a property (T_VARIABLE is nearby before this ';')
                for ($j = $i - 1; $j >= 0 && $j >= $i - 10; $j--) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_VARIABLE) {
                        $lastSemiOffset = $offsets[$i] + 1; // byte right after ';'
                        break;
                    }
                    if ($tokens[$j] === '(') {
                        break; // it is a method signature, not a property
                    }
                }
            }
        }

        if ($lastSemiOffset !== null) {
            return $lastSemiOffset;
        }

        // Fallback: right after the opening brace of the class body
        foreach ($tokens as $i => $tok) {
            if ($tok === '{') {
                return $offsets[$i] + 1; // byte right after '{'
            }
        }

        throw new RuntimeException("Could not determine insertion point in {$this->filePath}");
    }
}

class ParsedModel
{
    public function __construct(
        public string $filePath,
        public string $className,
        public ?string $classComment,
        /** @var array<string, ParsedProperty> name-keyed */
        public array $properties,
        public int $insertionOffset
    ) {}
}

class ParsedProperty
{
    public function __construct(
        public string $name,
        public ?string $type,
        public ?string $docComment
    ) {}
}