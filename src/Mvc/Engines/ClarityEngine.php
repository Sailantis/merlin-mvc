<?php
namespace Merlin\Mvc\Engines;

use Merlin\Mvc\Clarity\Cache;
use Merlin\Mvc\Clarity\ClarityException;
use Merlin\Mvc\Clarity\Compiler;
use Merlin\Mvc\Clarity\FunctionRegistry;
use Merlin\Mvc\ViewEngine;
use ParseError;

/**
 * Clarity template engine.
 *
 * Compiles `.clarity.html` templates into isolated PHP classes that are
 * cached on disk.  Templates have no access to arbitrary PHP — they can
 * only use the variables passed to render() and the registered filters.
 *
 * Usage
 * -----
 * ```php
 * $ctx->setView(new ClarityEngine());
 * $ctx->view()
 *     ->setPath(__DIR__ . '/../views')
 *     ->setLayout('layouts/main');
 *
 * // Register a custom filter
 * $ctx->view()->addFilter('currency', fn($v) => number_format($v, 2) . ' €');
 * ```
 *
 * Template extension: .clarity.html  (overridable via setExtension())
 *
 * Cache location: sys_get_temp_dir()/clarity  (configurable via setCachePath())
 */
class ClarityEngine extends ViewEngine
{
    private FunctionRegistry $functionRegistry;
    private Cache $cache;
    private Compiler $compiler;
    /** @var string[] */
    private array $renderStack = [];

    protected string $extension = '.clarity.html';

    public function __construct(array $vars = [])
    {
        parent::__construct($vars);

        $this->functionRegistry = new FunctionRegistry(
            fn(string $view, array $vars = []): string => $this->renderPartial($view, $vars)
        );
        $this->cache = new Cache();
        $this->compiler = new Compiler();
    }

    // -------------------------------------------------------------------------
    // Configuration pass-through
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function addFilter(string $name, callable $fn): static
    {
        $this->functionRegistry->addFilter($name, $fn);
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * The return value is automatically cast to a plain array/scalar/null
     * at the call site in compiled templates, preventing object leakage.
     */
    public function addFunction(string $name, callable $fn): static
    {
        $this->functionRegistry->addFunction($name, $fn);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCachePath(string $path): static
    {
        $this->cache->setPath($path);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCachePath(): string
    {
        return $this->cache->getPath();
    }

    /**
     * {@inheritdoc}
     */
    public function flushCache(): static
    {
        $this->cache->flush();
        return $this;
    }

    // -------------------------------------------------------------------------
    // ViewEngine abstract implementation
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function render(string $view, array $vars = []): string
    {
        $content = $this->renderPartial($view, $vars);

        if ($this->layout !== null && $this->renderDepth === 0) {
            $content = $this->renderLayout($this->layout, $content, $vars);
        }

        return $content;
    }

    /**
     * {@inheritdoc}
     */
    public function renderPartial(string $view, array $vars = []): string
    {
        $sourcePath = $this->resolveView($view);

        if (!is_file($sourcePath)) {
            throw new ClarityException("Template not found: {$sourcePath}", $sourcePath);
        }

        $this->renderDepth++;
        try {
            $merged = [...$this->vars, ...$vars];
            $cast = FunctionRegistry::castToArray($merged);
            $output = $this->renderFile($sourcePath, $cast);
        } finally {
            $this->renderDepth--;
        }

        return $output;
    }

    /**
     * {@inheritdoc}
     */
    public function renderLayout(string $layout, string $content, array $vars = []): string
    {
        $vars['content'] = $content;
        return $this->renderPartial($layout, $vars);
    }

    // -------------------------------------------------------------------------
    // Internal rendering
    // -------------------------------------------------------------------------

    /**
     * Compile (if needed) and render a single template file.
     *
     * @param string $sourcePath Absolute path to the .clarity.html file.
     * @param array  $vars  Already-cast variables array.
     * @return string Rendered output.
     * @throws ClarityException On compile or runtime errors.
     */
    private function renderFile(string $sourcePath, array $vars): string
    {
        if (isset($this->renderStack[$sourcePath])) {
            $chain = [...array_keys($this->renderStack), $sourcePath];
            throw new ClarityException(
                'Recursive template rendering detected: ' . \implode(' -> ', $chain),
                $sourcePath
            );
        }

        $this->renderStack[$sourcePath] = true;

        // Ensure compiled class is loaded
        try {
            $className = $this->loadCachedClass($sourcePath);

            // Instantiate with filter and function registries
            $template = new $className(
                $this->functionRegistry->allFilters(),
                $this->functionRegistry->allFunctions(),
                [FunctionRegistry::class, 'castToArray']
            );

            // Install error handler to map PHP errors → template lines
            set_error_handler(
                $this->buildErrorHandler($sourcePath),
                E_ALL
            );

            try {
                $output = $template->render($vars);
            } finally {
                restore_error_handler();
            }
        } finally {
            unset($this->renderStack[$sourcePath]);
        }

        return $output;
    }

    /**
     * Return an already-loaded class name, compiling & caching as needed.
     *
     * @return class-string
     */
    private function loadCachedClass(string $sourcePath): string
    {
        if ($this->cache->isFresh($sourcePath)) {
            try {
                $className = $this->cache->load($sourcePath);
            } catch (ParseError) {
                // A previously-written cache file contains invalid PHP (e.g. a
                // template that was broken at write time and not yet cleaned up).
                // Delete it so the next step triggers a fresh compile.
                $this->cache->invalidate($sourcePath);
                $className = null;
            }
            if ($className !== null) {
                return $className;
            }
        }

        // Compile and write; the cache file is required inside writeAndLoad()
        // using plain `require` so the new versioned class is always declared.
        $this->syncCompilerConfig();
        $compiled = $this->compiler->compile($sourcePath);
        try {
            return $this->cache->writeAndLoad($sourcePath, $compiled);
        } catch (ParseError $e) {
            // The compiled PHP contains a syntax error (e.g. a malformed expression
            // in the template).  Delete the broken cache file so the next request
            // does not serve an unloadable file, then map the error back to the
            // original template line using the source map we already have.
            $this->cache->invalidate($sourcePath);
            [$tplFile, $tplLine] = $this->mapCompiledErrorLine(
                $e->getLine(),
                $compiled->code,
                $compiled->sourceMap,
                $compiled->sourceFiles,
                $sourcePath
            );
            throw new ClarityException(
                'Syntax error in template: ' . $e->getMessage(),
                $tplFile ?? $sourcePath,
                $tplLine,
                $e
            );
        }
    }

    /**
     * Map a file line number from a compiled cache file back to the original
     * template file and line, using only the source map from a CompiledTemplate
     * (no class loading or reflection required).
     *
     * Cache::writeAndLoad() prepends "<?php\n" before the compiled code, so
     * the body does not start at line 1.  The preamble emitted by buildClass()
     * is variable-length (deps/sourceMap exports span multiple lines), so the
     * offset is determined dynamically by locating the "ob_start()" sentinel
     * that marks the start of the render() body.
     *
     * @param int      $fileLine     1-based line number reported by the ParseError.
     * @param string   $compiledCode The compiled PHP code from CompiledTemplate (no leading <?php).
     * @param array    $sourceMap    Source map from the CompiledTemplate.
     * @param string[] $files        Source file paths (indexed by the integers in $sourceMap).
     * @param string   $sourcePath   Fallback template file path.
     * @return array{0: string|null, 1: int}  [templateFile|null, templateLine]
     */
    private function mapCompiledErrorLine(int $fileLine, string $compiledCode, array $sourceMap, array $files, string $sourcePath): array
    {
        if ($sourceMap === []) {
            return [null, 0];
        }

        // Locate the line that contains "ob_start()" inside the full file
        // (compiled code prefixed by the "<?php\n" that Cache adds).
        $fileLines = explode("\n", "<?php\n" . $compiledCode);
        $bodyStartFileLine = 0;
        foreach ($fileLines as $i => $line) {
            if (str_contains($line, 'ob_start()')) {
                $bodyStartFileLine = $i + 1; // 0-indexed → 1-indexed
                break;
            }
        }

        if ($bodyStartFileLine === 0) {
            return [null, 0];
        }

        // Convert the absolute file line to a body-relative line, which is
        // what the source map's phpLineStart values are indexed against.
        $bodyLine = $fileLine - $bodyStartFileLine + 1;

        // Find the last source-map range whose phpLineStart ≤ $bodyLine.
        $matched = null;
        foreach ($sourceMap as $range) {
            if ($range[0] <= $bodyLine) {
                $matched = $range;
            } else {
                break;
            }
        }

        if ($matched === null) {
            return [null, 0];
        }
        $tplFile = $files[$matched[1]] ?? null;
        return [$tplFile, $matched[2]];
    }

    /**
     * Keep the compiler in sync with engine configuration (path, extension,
     * namespaces).  Called before every fresh compile.
     */
    private function syncCompilerConfig(): void
    {
        $this->compiler
            ->setBasePath($this->viewPath)
            ->setExtension($this->extension)
            ->setNamespaces($this->namespaces)
            ->setFilterRegistry($this->functionRegistry);
    }

    /**
     * Build an error-handler closure that maps a PHP error in the compiled
     * cache file back to the original template file and line.
     *
     * @param string $sourcePath The entry template source path.
     * @return callable
     */
    private function buildErrorHandler(string $sourcePath): callable
    {
        $cacheFile = $this->cache->cacheFilePath($sourcePath);

        return function (int $errno, string $errstr, string $errfile, int $errline) use ($sourcePath, $cacheFile): bool {
            if (realpath($errfile) !== realpath($cacheFile)) {
                // Error is not in our compiled file – let it propagate normally
                return false;
            }

            [$tplFile, $tplLine] = $this->resolveTemplateLine($sourcePath, $errline);
            throw new ClarityException($errstr, $tplFile ?? $sourcePath, $tplLine);
        };
    }

    /**
     * Map a PHP line number in the compiled cache file back to the original
     * template file and line number using the $sourceMap static property on
     * the compiled class — no file I/O required.
     *
     * The source map is a list of ranges: [phpLineStart, fileIndex, templateLine].
     * The matching range is the last entry whose phpLineStart ≤ $phpLine.
     * File paths are resolved from the parallel $files static property.
     *
     * @param string $sourcePath Absolute path to the entry template.
     * @param int    $phpLine    Line number of the error in the compiled file.
     * @return array{0: string|null, 1: int}  [templateFile|null, templateLine]
     */
    private function resolveTemplateLine(string $sourcePath, int $phpLine): array
    {
        $className = $this->cache->getLoadedClassName($sourcePath);
        if ($className === null) {
            return [null, 0];
        }

        try {
            $map = $className::$sourceMap;
            $files = $className::$sourceFiles;
        } catch (\Error) {
            return [null, 0];
        }

        if (!\is_array($map) || $map === []) {
            return [null, 0];
        }

        // Ranges are sorted by phpLineStart ascending; find the last one ≤ phpLine.
        $matched = null;
        foreach ($map as $range) {
            if ($range[0] <= $phpLine) {
                $matched = $range;
            } else {
                break;
            }
        }

        if ($matched === null) {
            return [null, 0];
        }
        $tplFile = $files[$matched[1]] ?? null;
        return [$tplFile, $matched[2]];
    }

}
