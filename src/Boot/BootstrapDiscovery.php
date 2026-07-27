<?php
namespace Azera\Boot;

/**
 * Discovers BootstrapProvider implementations by scanning PSR-4 autoload
 * directories declared in the project's composer.json.
 *
 * No dependency on Composer's runtime API — just reads the JSON, walks
 * the directories, and token-scans each .php file for a class that
 * implements BootstrapProvider.
 *
 * The result is cached to vendor/azera-bootstrap.php so the resolver
 * does not repeat the scan on every invocation.
 */
final class BootstrapDiscovery
{
    private const CACHE_FILENAME = 'azera-bootstrap.php';

    /**
     * Scan the project for a BootstrapProvider implementation and write
     * the result to vendor/azera-bootstrap.php.
     *
     * If a cache file already exists and is fresh, the scan is skipped.
     */
    public static function scanAndCache(string $projectRoot): void
    {
        $vendorDir = $projectRoot . '/vendor';
        if (!is_dir($vendorDir)) {
            return;
        }

        $cacheFile = $vendorDir . '/' . self::CACHE_FILENAME;

        // If cache exists and is still fresh, skip.
        if (is_file($cacheFile)) {
            $cached = require $cacheFile;
            if (is_array($cached) && self::isFresh($cached, $projectRoot)) {
                return;
            }
        }

        $provider = self::scanForProvider($projectRoot);
        self::writeCache($vendorDir, $provider);
    }

    /**
     * Scan the project's PSR-4 autoload directories for classes
     * implementing BootstrapProvider.
     *
     * Returns the FQCN of the discovered provider, or null if none found.
     * If multiple candidates exist, prefers one named "…\Bootstrap".
     */
    public static function scanForProvider(string $projectRoot): ?string
    {
        $candidates = self::collectCandidates($projectRoot);

        if ($candidates === []) {
            return null;
        }

        // Prefer a class named "Bootstrap" (convention over configuration).
        foreach ($candidates as $fqcn) {
            if (str_ends_with($fqcn, '\\Bootstrap')) {
                return $fqcn;
            }
        }

        return $candidates[0];
    }

    /**
     * Read composer.json, resolve PSR-4 autoload directories, walk them,
     * and token-scan each .php file for `implements BootstrapProvider`.
     *
     * @return list<string> FQCNs of candidate classes.
     */
    private static function collectCandidates(string $projectRoot): array
    {
        $composer = $projectRoot . '/composer.json';
        if (!is_file($composer)) {
            return [];
        }

        $json = json_decode(file_get_contents($composer), true);
        if (!is_array($json)) {
            return [];
        }

        // Merge autoload + autoload-dev PSR-4 mappings.
        $psr4 = [];
        foreach (['autoload', 'autoload-dev'] as $section) {
            if (isset($json[$section]['psr-4']) && is_array($json[$section]['psr-4'])) {
                foreach ($json[$section]['psr-4'] as $namespace => $paths) {
                    $paths = (array) $paths;
                    foreach ($paths as $path) {
                        $psr4[] = [$namespace, $path];
                    }
                }
            }
        }

        // Also scan classmap entries if present.
        $classmapPaths = [];
        foreach (['autoload', 'autoload-dev'] as $section) {
            if (isset($json[$section]['classmap']) && is_array($json[$section]['classmap'])) {
                foreach ($json[$section]['classmap'] as $path) {
                    $classmapPaths[] = $path;
                }
            }
        }

        $candidates = [];

        // Walk PSR-4 directories.
        foreach ($psr4 as [$namespace, $relPath]) {
            $absPath = $projectRoot . '/' . $relPath;
            if (!is_dir($absPath)) {
                continue;
            }
            self::walkPsr4Dir($absPath, $namespace, $candidates);
        }

        // Walk classmap directories (derive FQCN from tokens).
        foreach ($classmapPaths as $relPath) {
            $absPath = $projectRoot . '/' . $relPath;
            if (is_dir($absPath)) {
                self::walkClassmapDir($absPath, $candidates);
            } elseif (is_file($absPath) && str_ends_with($absPath, '.php')) {
                $fqcn = self::scanFileForProvider($absPath);
                if ($fqcn !== null) {
                    $candidates[] = $fqcn;
                }
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Recursively walk a PSR-4 directory, deriving FQCN from the namespace
     * prefix + relative path.
     */
    private static function walkPsr4Dir(string $dir, string $namespace, array &$candidates): void
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $dir . '/' . $entry;

            if (is_dir($fullPath)) {
                $subNamespace = $namespace . $entry . '\\';
                self::walkPsr4Dir($fullPath, $subNamespace, $candidates);
            } elseif (str_ends_with($entry, '.php')) {
                $className = substr($entry, 0, -4);
                $fqcn      = rtrim($namespace, '\\') . '\\' . $className;

                // Token-scan to verify the class actually implements BootstrapProvider.
                if (self::fileImplementsProvider($fullPath)) {
                    $candidates[] = ltrim($fqcn, '\\');
                }
            }
        }
    }

    /**
     * Walk a classmap directory — derive FQCN from the file's namespace
     * and class declaration tokens.
     */
    private static function walkClassmapDir(string $dir, array &$candidates): void
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $dir . '/' . $entry;

            if (is_dir($fullPath)) {
                self::walkClassmapDir($fullPath, $candidates);
            } elseif (str_ends_with($entry, '.php')) {
                $fqcn = self::scanFileForProvider($fullPath);
                if ($fqcn !== null) {
                    $candidates[] = $fqcn;
                }
            }
        }
    }

    /**
     * Token-scan a PHP file: return the FQCN of the class declared in it
     * if that class implements BootstrapProvider, otherwise null.
     */
    private static function scanFileForProvider(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $source = @file_get_contents($path);
        if ($source === false) {
            return null;
        }

        $tokens     = token_get_all($source);
        $tokenCount = count($tokens);

        $namespace  = '';
        $className  = null;
        $implements = false;

        // First pass: extract namespace.
        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $j  = $i + 1;
                $ns = '';
                while ($j < $tokenCount) {
                    $t = $tokens[$j];
                    if (is_string($t) && ($t === ';' || $t === '{')) {
                        break;
                    }
                    if (is_array($t)) {
                        $ns .= $t[1];
                    } else {
                        $ns .= $t;
                    }
                    $j++;
                }
                $namespace = trim($ns);
                break;
            }
        }

        // Second pass: find class declaration + implements clause.
        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && ($token[0] === T_CLASS || (defined('T_READONLY_CLASS') && $token[0] === T_READONLY_CLASS))) {
                // Skip anonymous classes (T_CLASS not followed by T_STRING).
                $j = $i + 1;
                while ($j < $tokenCount && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }

                if ($j >= $tokenCount || !is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) {
                    continue;
                }

                $className = $tokens[$j][1];

                // Skip past class name.
                $j++;
                while ($j < $tokenCount && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }

                // Skip "extends ParentClass" if present.
                if ($j < $tokenCount && is_array($tokens[$j]) && $tokens[$j][0] === T_EXTENDS) {
                    $j++;
                    while ($j < $tokenCount) {
                        $t = $tokens[$j];
                        if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR, T_WHITESPACE], true)) {
                            $j++;
                        } else {
                            break;
                        }
                    }
                    while ($j < $tokenCount && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                        $j++;
                    }
                }

                // Check for "implements".
                if ($j < $tokenCount && is_array($tokens[$j]) && $tokens[$j][0] === T_IMPLEMENTS) {
                    $implementsLine = '';
                    while ($j < $tokenCount) {
                        $t = $tokens[$j];
                        if (is_string($t) && $t === '{') {
                            break;
                        }
                        if (is_array($t)) {
                            $implementsLine .= $t[1];
                        } else {
                            $implementsLine .= $t;
                        }
                        $j++;
                    }

                    if (
                        preg_match(
                        '/\\\\?Azera\\\\Boot\\\\BootstrapProvider\b|(?<![\w\\\\])BootstrapProvider\b/',
                        $implementsLine
                                            )
                    ) {
                        $implements = true;
                        break;
                    }
                }
            }
        }

        if ($className === null || !$implements) {
            return null;
        }

        return ($namespace !== '' ? $namespace . '\\' : '') . $className;
    }

    /**
     * Quick check: does the file at $path declare a class that implements
     * BootstrapProvider? (Used by the PSR-4 walker where FQCN is already
     * known from the path.)
     */
    private static function fileImplementsProvider(string $path): bool
    {
        return self::scanFileForProvider($path) !== null;
    }

    /**
     * Write the generated cache file to vendor/azera-bootstrap.php.
     *
     * Made public so BootstrapResolver can persist a --save choice.
     */
    public static function writeCache(string $vendorDir, ?string $provider): void
    {
        $cacheFile = $vendorDir . '/' . self::CACHE_FILENAME;

        $providerLiteral = $provider === null ? 'null' : var_export($provider, true);
        $timestamp       = date('c');

        $contents = <<<PHP
<?php
// This file is auto-generated by Azera\\Boot\\BootstrapDiscovery.
// Do not edit manually. It is refreshed by the `azera` CLI when stale.

return [
    'provider'  => {$providerLiteral},
    'generated' => '{$timestamp}',
];
PHP;

        @file_put_contents($cacheFile, $contents);
    }

    /**
     * Persist a provider FQCN to the discovery cache.
     *
     * Called by the resolver when --save is passed alongside --provider=.
     */
    public static function saveProvider(string $projectRoot, string $provider): void
    {
        $vendorDir = $projectRoot . '/vendor';
        if (!is_dir($vendorDir)) {
            return;
        }
        self::writeCache($vendorDir, $provider);
    }

    /**
     * Check whether the cached discovery result is still fresh.
     *
     * We consider it fresh if the cache was generated after the most
     * recent modification of composer.json. This avoids re-scanning
     * on every CLI invocation while still picking up autoload changes.
     */
    private static function isFresh(array $cached, string $projectRoot): bool
    {
        if (empty($cached['generated'])) {
            return false;
        }

        $composerFile = $projectRoot . '/composer.json';
        if (!is_file($composerFile)) {
            return true;
        }

        $cacheTime    = strtotime($cached['generated']);
        $composerTime = filemtime($composerFile);

        return $cacheTime !== false && $cacheTime >= $composerTime;
    }
}