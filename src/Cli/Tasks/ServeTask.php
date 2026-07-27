<?php

namespace Azera\Cli\Tasks;

use Azera\Cli\Task;

/**
 * Start the PHP built-in development server.
 *
 * Usage:
 *   azera serve [--host=<addr>] [--port=<n>] [--docroot=<dir>]
 *
 * Options:
 *   --host=<addr>     Host address to bind to (default: 0.0.0.0)
 *   --port=<n>        Port number to listen on (default: 8000)
 *   --docroot=<dir>   Document root directory relative to project root
 *                     (default: public)
 *
 * Examples:
 *   azera serve                       # start on 0.0.0.0:8000
 *   azera serve --port=8888           # start on port 8888
 *   azera serve --host=127.0.0.1      # bind to localhost only
 */
class ServeTask extends Task
{
    public function runAction(): void
    {
        $projectRoot = $this->console->findComposerRoot();
        if ($projectRoot === null) {
            $this->error('No project root found (composer.json not detected).');
            return;
        }

        $host    = $this->option('host', '0.0.0.0');
        $port    = (string) $this->option('port', '8000');
        $docroot = (string) $this->option('docroot', 'public');

        // Resolve docroot relative to project root
        $docrootPath = $projectRoot . DIRECTORY_SEPARATOR . $docroot;
        if (!is_dir($docrootPath)) {
            $this->error("Document root not found: {$docrootPath}");
            $this->muted('  Run "azera init" to scaffold the project structure.');
            return;
        }

        // The router script is the docroot's index.php
        $routerScript = $docrootPath . DIRECTORY_SEPARATOR . 'index.php';
        if (!is_file($routerScript)) {
            $this->error("No index.php found in document root: {$docrootPath}");
            $this->muted('  Run "azera init" to scaffold the project structure.');
            return;
        }

        // Find the PHP executable
        $phpBin = PHP_BINARY;
        if ($phpBin === '') {
            $this->error('PHP executable not found.');
            return;
        }

        // Build the command
        // php -S host:port -t docroot docroot/index.php
        $cmd = sprintf(
            '%s -S %s:%s -t %s %s',
            escapeshellarg($phpBin),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($docrootPath),
            escapeshellarg($routerScript)
        );

        // Print banner
        $this->line('');
        $this->writeln($this->style(' Azera Development Server ', 'bold', 'bg-green'));
        $this->line('');

        $displayHost = $host === '0.0.0.0' ? 'localhost' : $host;
        $url         = "http://{$displayHost}:{$port}";
        $this->info("  Server running at {$url}");
        $this->muted("  Document root: {$docrootPath}");
        $this->muted("  Router script: {$routerScript}");
        $this->line('');
        $this->muted('  Press Ctrl+C to stop.');
        $this->line('');

        // Run the server in the foreground
        passthru($cmd, $exitCode);

        if ($exitCode !== 0) {
            $this->error("Server exited with code {$exitCode}.");
        }
    }
}