<?php
namespace Merlin\Mvc\Clarity;

/**
 * Value object produced by the Clarity Compiler for a single template file.
 *
 * @property-read string $className   Fully-qualified class name of the compiled template.
 * @property-read string $code        Complete PHP source code of the compiled class.
 * @property-read array  $sourceMap   Maps PHP line numbers → original template line numbers.
 * @property-read array  $dependencies Associative array of [absoluteFilePath => mtime]
 *                                     for every file (entry + extends + includes) read
 *                                     during compilation. Used for cache invalidation.
 */
class CompiledTemplate
{
    /**
     * @param string $className   Generated class name (e.g. __Clarity_f1f1fde8ef8cc7825f199f1b7bf3ad0e).
     * @param string $code        Full PHP source of the compiled file.
     * @param array  $sourceMap   [phpLine => templateLine] mapping.
     * @param array  $dependencies [absolutePath => mtime] for cache invalidation.
     */
    public function __construct(
        public readonly string $className,
        public readonly string $code,
        public readonly array $sourceMap,
        public readonly array $dependencies
    ) {
    }
}
