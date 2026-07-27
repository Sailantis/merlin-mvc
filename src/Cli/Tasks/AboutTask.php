<?php

namespace Azera\Cli\Tasks;

use Azera\AppContext;
use Azera\Cli\Task;
use Azera\Boot\BootstrapResolver;

/**
 * Print diagnostic information about the current Azera project.
 *
 * Usage:
 *   azera about
 *
 * Shows framework version, PHP version, detected project root, resolved
 * bootstrap class, registered database roles, view engine, and loaded
 * extensions.
 */
class AboutTask extends Task
{
    public function runAction(): void
    {
        $projectRoot = $this->console->findComposerRoot();
        $ctx         = AppContext::instance();

        // --- Azera framework version ---
        $fwVersion = $this->detectFrameworkVersion($projectRoot);

        // --- Bootstrap class ---
        $bridgeClass = 'none';
        if ($projectRoot !== null) {
            $bridge = BootstrapResolver::resolve($projectRoot);
            if ($bridge !== null) {
                $bridgeClass = $bridge::class;
            }
        }

        // --- Database roles ---
        $dbRoles = $this->safeDbRoles($ctx);

        // --- View engine ---
        $viewEngine = $this->safeViewEngine($ctx);

        // --- Extensions ---
        $extensions = $this->checkExtensions();

        // --- Render ---
        $this->printSection('Azera Framework');
        $this->printRow('Version', $fwVersion);
        $this->printRow('Project root', $projectRoot ?? 'not detected');
        $this->printRow('Bootstrap', $bridgeClass);
        $this->printRow('PHP', PHP_VERSION . ' (' . PHP_SAPI . ')');
        $this->printRow('View engine', $viewEngine);

        $this->line('');
        $this->printSection('Database');
        if (empty($dbRoles)) {
            $this->muted('  No database connections registered.');
        } else {
            foreach ($dbRoles as $role) {
                $driver = $this->safeDbDriver($ctx, $role);
                $this->printRow($role, $driver);
            }
        }

        $this->line('');
        $this->printSection('Extensions');
        foreach ($extensions as $name => $loaded) {
            $label = $loaded
                ? $this->style('loaded', 'bgreen')
                : $this->style('missing', 'bred');
            $this->writeln("  {$name}  {$label}");
        }
    }

    // -----------------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------------

    private function printSection(string $title): void
    {
        $this->writeln($this->style($title, 'bold', 'bgreen'));
    }

    private function printRow(string $label, string $value): void
    {
        $label = $this->style($label, 'cyan');
        $this->writeln("  {$label}  {$value}");
    }

    private function detectFrameworkVersion(?string $projectRoot): string
    {
        $fwComposer = dirname(__DIR__, 2) . '/composer.json';
        if (is_file($fwComposer)) {
            $json = json_decode(file_get_contents($fwComposer), true);
            $ver  = $json['version'] ?? null;
            if ($ver !== null) {
                return $ver;
            }
        }
        return 'dev';
    }

    /**
     * @return string[]  role name => driver name (or 'not connected')
     */
    private function safeDbRoles(AppContext $ctx): array
    {
        try {
            $roles = $ctx->dbManager()->roles();
            return empty($roles) ? [] : array_combine($roles, $roles);
        } catch (\Throwable) {
            return [];
        }
    }

    private function safeDbDriver(AppContext $ctx, string $role): string
    {
        try {
            $db = $ctx->dbManager()->get($role);
            return $db->getDriver();
        } catch (\Throwable) {
            return 'not connected';
        }
    }

    private function safeViewEngine(AppContext $ctx): string
    {
        try {
            return $ctx->view()::class;
        } catch (\Throwable) {
            return 'not initialized';
        }
    }

    /**
     * @return array<string,bool> extension name => loaded
     */
    private function checkExtensions(): array
    {
        $required = ['pdo', 'mbstring'];
        $optional = ['curl', 'openssl', 'sodium', 'imap', 'gd', 'zip'];
        $result   = [];
        foreach ($required as $ext) {
            $result[$ext] = extension_loaded($ext);
        }
        foreach ($optional as $ext) {
            $result[$ext] = extension_loaded($ext);
        }
        return $result;
    }
}