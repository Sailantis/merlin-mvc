<?php

namespace Azera\Cli\Tasks;

use Azera\AppContext;
use Azera\Cli\Task;

/**
 * Clear or inspect framework caches.
 *
 * Usage:
 *   cache:clear                   Clear all known caches
 *   cache:status                  Show cache file information
 *
 * The bootstrap discovery cache (vendor/azera-bootstrap.php) and
 * the Clarity compiled template cache (sys_get_temp_dir()/clarity)
 * are managed by this task. No AppContext boot is required — the
 * cache files are deleted directly.
 *
 * Options:
 *   --only=<target>    Only clear a specific cache: "bootstrap" or "clarity"
 *   --path=<dir>       Custom Clarity cache directory (overrides default)
 *
 * Examples:
 *   cache:clear
 *   cache:clear --only=bootstrap
 *   cache:clear --only=clarity --path=/tmp/my-cache
 *   cache:status
 */
class CacheTask extends Task
{
    private const BOOTSTRAP_CACHE_NAME = 'azera-bootstrap.php';

    /**
     * Clear all known caches.
     */
    public function clearAction(): void
    {
        $projectRoot = $this->console->findComposerRoot();
        $only        = $this->option('only');
        $cleared     = ['bootstrap' => 0, 'clarity' => 0];

        if ($only === null || $only === 'bootstrap') {
            $cleared['bootstrap'] = $this->clearBootstrapCache($projectRoot);
        }

        if ($only === null || $only === 'clarity') {
            $cleared['clarity'] = $this->clearClarityCache();
        }

        $total = array_sum($cleared);
        if ($total === 0) {
            $this->muted('No cache files found to clear.');
            return;
        }

        $parts = [];
        if ($cleared['bootstrap'] > 0) {
            $parts[] = $cleared['bootstrap'] . ' bootstrap';
        }
        if ($cleared['clarity'] > 0) {
            $parts[] = $cleared['clarity'] . ' template';
        }
        $this->success('Cleared ' . implode(' + ', $parts) . ' cache file(s).');
    }

    /**
     * Show information about existing cache files.
     */
    public function statusAction(): void
    {
        $projectRoot = $this->console->findComposerRoot();

        $this->line($this->style('Cache Status', 'bold', 'bgreen'));
        $this->line('');

        // Bootstrap cache
        $this->printBootstrapStatus($projectRoot);

        // Clarity template cache
        $this->printClarityStatus();
    }

    // -------------------------------------------------------------------------
    //  Bootstrap cache
    // -------------------------------------------------------------------------

    private function clearBootstrapCache(?string $projectRoot): int
    {
        if ($projectRoot === null) {
            $this->muted('No project root found — skipping bootstrap cache.');
            return 0;
        }

        $cacheFile = $projectRoot . '/vendor/' . self::BOOTSTRAP_CACHE_NAME;
        if (!is_file($cacheFile)) {
            return 0;
        }

        unlink($cacheFile);
        $this->muted("Deleted {$cacheFile}");
        return 1;
    }

    private function printBootstrapStatus(?string $projectRoot): void
    {
        $label = $this->style('bootstrap', 'cyan');
        if ($projectRoot === null) {
            $this->writeln("  {$label}  " . $this->style('project root not found', 'yellow'));
            return;
        }

        $cacheFile = $projectRoot . '/vendor/' . self::BOOTSTRAP_CACHE_NAME;
        if (!is_file($cacheFile)) {
            $this->writeln("  {$label}  " . $this->style('not found (will regenerate on next boot)', 'gray'));
            return;
        }

        $size = filesize($cacheFile);
        $time = date('Y-m-d H:i:s', filemtime($cacheFile));
        $this->writeln("  {$label}  {$size} bytes, modified {$time}");
    }

    // -------------------------------------------------------------------------
    //  Clarity template cache
    // -------------------------------------------------------------------------

    private function getClarityCachePath(): string
    {
        $custom = $this->option('path');
        if ($custom !== null && is_dir($custom)) {
            return rtrim($custom, '/\\');
        }
        try {
            return AppContext::instance()->view()->getCachePath();
        } catch (\Throwable) {
            return '';
        }
    }

    private function clearClarityCache(): int
    {
        $dir = $this->getClarityCachePath();
        if (!is_dir($dir)) {
            return 0;
        }

        $count    = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                unlink($file->getRealPath());
                $count++;
            }
        }

        if ($count > 0) {
            $this->muted("Deleted {$count} compiled template file(s) from {$dir}");
        }

        return $count;
    }

    private function printClarityStatus(): void
    {
        $dir   = $this->getClarityCachePath();
        $label = $this->style('clarity', 'cyan');

        if (!is_dir($dir)) {
            $this->writeln("  {$label}  " . $this->style('cache directory not found', 'gray'));
            return;
        }

        $count    = 0;
        $bytes    = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $count++;
                $bytes += $file->getSize();
            }
        }

        if ($count === 0) {
            $this->writeln("  {$label}  " . $this->style('empty', 'gray'));
            return;
        }

        $sizeStr = $bytes > 1024 * 1024
            ? round($bytes / 1024 / 1024, 1) . ' MB'
            : ($bytes > 1024 ? round($bytes / 1024, 1) . ' KB' : $bytes . ' bytes');
        $this->writeln("  {$label}  {$count} file(s), {$sizeStr}");
    }
}