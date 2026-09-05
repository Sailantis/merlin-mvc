<?php

namespace Azera\Cli\Tasks;

use Azera\Cli\Task;

/**
 * Scaffold boilerplate files in the correct PSR-4 directories.
 *
 * Usage:
 *   make:controller <Name>     Create a controller class
 *   make:model <Name>          Create a model class
 *   make:task <Name>           Create a CLI task class
 *   make:middleware <Name>     Create a middleware class
 *   make:view <name>           Create a Clarity template file
 *
 * Options:
 *   --force            Overwrite existing files
 *   --namespace=<ns>   Override the auto-detected root namespace
 *
 * Examples:
 *   make:controller Foo
 *   make:model User --force
 *   make:task SyncPrices
 *   make:middleware Auth
 *   make:view home
 *   make:controller Foo --namespace=App\\Admin
 */
class MakeTask extends Task
{
    /**
     * Create a controller class.
     *
     * @param string $name Controller name (e.g. "Foo" or "UserController")
     */
    public function controllerAction(string $name = ''): void
    {
        if ($name === '') {
            $this->error('Please specify a name: azera make:controller <Name>');
            return;
        }

        $className = $this->normalizeClassName($name, 'Controller');
        $ns = $this->resolveNamespace('Controllers');
        $dir = $this->resolveDirectory('Controllers');
        $filePath = $dir . DIRECTORY_SEPARATOR . $className . '.php';

        $content = $this->renderStub($className, $ns, 'controller');
        $this->writeScaffold($filePath, $content, "Controller {$className}");
    }

    /**
     * Create a model class.
     *
     * @param string $name Model name (e.g. "User" or "BlogPost")
     */
    public function modelAction(string $name = ''): void
    {
        if ($name === '') {
            $this->error('Please specify a name: azera make:model <Name>');
            return;
        }

        $className = $this->normalizeClassName($name, '');
        $ns = $this->resolveNamespace('Models');
        $dir = $this->resolveDirectory('Models');
        $filePath = $dir . DIRECTORY_SEPARATOR . $className . '.php';
        $tableName = $this->deriveTableName($className);

        $content = $this->renderModelStub($className, $ns, $tableName);
        $this->writeScaffold($filePath, $content, "Model {$className}");
    }

    /**
     * Create a CLI task class.
     *
     * @param string $name Task name (e.g. "SyncPrices" or "SendEmails")
     */
    public function taskAction(string $name = ''): void
    {
        if ($name === '') {
            $this->error('Please specify a name: azera make:task <Name>');
            return;
        }

        $className = $this->normalizeClassName($name, 'Task');
        $ns = $this->resolveNamespace('Tasks');
        $dir = $this->resolveDirectory('Tasks');
        $filePath = $dir . DIRECTORY_SEPARATOR . $className . '.php';

        $content = $this->renderStub($className, $ns, 'task');
        $this->writeScaffold($filePath, $content, "Task {$className}");
    }

    /**
     * Create a middleware class.
     *
     * @param string $name Middleware name (e.g. "Auth" or "Cors")
     */
    public function middlewareAction(string $name = ''): void
    {
        if ($name === '') {
            $this->error('Please specify a name: azera make:middleware <Name>');
            return;
        }

        $className = $this->normalizeClassName($name, '');
        $ns = $this->resolveNamespace('Middleware');
        $dir = $this->resolveDirectory('Middleware');
        $filePath = $dir . DIRECTORY_SEPARATOR . $className . '.php';

        $content = $this->renderMiddlewareStub($className, $ns);
        $this->writeScaffold($filePath, $content, "Middleware {$className}");
    }

    /**
     * Create a Clarity template view file.
     *
     * @param string $name View name (e.g. "home", "users.index")
     */
    public function viewAction(string $name = ''): void
    {
        if ($name === '') {
            $this->error('Please specify a name: azera make:view <name>');
            return;
        }

        $dir = $this->resolveViewDirectory();
        if ($dir === null) {
            $this->error('Could not determine views directory.');
            $this->muted('Create a "views" directory or set the view path in your Bootstrap.');
            return;
        }

        // Convert dot notation to directory structure: "users.index" → "users/index.clarity.html"
        $relativePath = str_replace('.', DIRECTORY_SEPARATOR, $name) . '.clarity.html';
        $filePath = $dir . DIRECTORY_SEPARATOR . $relativePath;

        if (is_file($filePath) && !$this->option('force', false)) {
            $this->warn("File already exists: {$filePath}");
            $this->muted('  Use --force to overwrite.');
            return;
        }

        $content = $this->renderViewStub($name);
        $this->ensureDirectory(dirname($filePath));
        file_put_contents($filePath, $content);

        $relDisplay = $this->relativePath($filePath);
        $this->success("Created {$relDisplay}");
    }

    // -------------------------------------------------------------------------
    //  Stub templates
    // -------------------------------------------------------------------------

    private function renderStub(string $className, string $namespace, string $type): string
    {
        $methods = match ($type) {
            'controller' => $this->controllerMethods(),
            'task' => $this->taskMethods(),
            default => ''
        };

        return <<<PHP
        <?php

        namespace {$namespace};

PHP
            . ($type === 'controller'
            ? ''
            : "use Azera\Cli\Task;\n\n")
            . <<<PHP
        class {$className}
        {
        {$methods}
        }

PHP;
    }

    private function controllerMethods(): string
    {
        return <<<'PHP'
    /**
     * Display the index page.
     */
    public function indexAction(): void
    {
        $this->view('home');
    }

PHP;
    }

    private function taskMethods(): string
    {
        return <<<'PHP'
    /**
     * Run the task.
     */
    public function runAction(): void
    {
        $this->info('Task started.');
        // TODO: implement task logic
        $this->success('Task completed.');
    }

PHP;
    }

    private function renderModelStub(string $className, string $namespace, string $tableName): string
    {
        return <<<PHP
        <?php

        namespace {$namespace};

        use Azera\Orm\Model;

        class {$className} extends Model
        {
            /**
             * The database table associated with this model.
             */
            public function source(): string
            {
                return '{$tableName}';
            }
        }

        PHP;
    }

    private function renderMiddlewareStub(string $className, string $namespace): string
    {
        return <<<PHP
        <?php

        namespace {$namespace};

        class {$className}
        {
            /**
             * Handle an incoming request.
             *
             * @param callable \$next Call the next handler in the pipeline
             */
            public function handle(callable \$next): void
            {
                // TODO: implement middleware logic
                \$next();
            }
        }

        PHP;
    }

    private function renderViewStub(string $name): string
    {
        return <<<HTML
        <!-- {$name} -->
        <h1>{$name}</h1>

        HTML;
    }

    // -------------------------------------------------------------------------
    //  Namespace and directory resolution
    // -------------------------------------------------------------------------

    /**
     * Resolve the root namespace from composer.json PSR-4, then append
     * the type sub-namespace.
     * @param string $type
     */
    private function resolveNamespace(string $type): string
    {
        $override = $this->option('namespace');
        if ($override !== null) {
            return rtrim($override, '\\') . '\\' . $type;
        }

        $rootNs = $this->detectRootNamespace();
        return $rootNs . '\\' . $type;
    }

    /**
     * Resolve the filesystem directory for a given type using PSR-4.
     * @param string $type
     */
    private function resolveDirectory(string $type): string
    {
        $ns = $this->resolveNamespace($type);
        $path = $this->console->resolvePsr4Path($ns);
        if ($path !== null && is_dir($path)) {
            return $path;
        }

        // Fallback: try common conventions relative to project root
        $projectRoot = $this->console->findComposerRoot();
        if ($projectRoot !== null) {
            foreach (["app/{$type}", "src/{$type}", "App/{$type}"] as $rel) {
                $abs = $projectRoot . '/' . $rel;
                if (is_dir($abs)) {
                    return $abs;
                }
            }
            // Create app/Type as last resort
            $abs = $projectRoot . '/app/' . $type;
            $this->ensureDirectory($abs);
            return $abs;
        }

        // Absolute fallback
        $abs = getcwd() . '/app/' . $type;
        $this->ensureDirectory($abs);
        return $abs;
    }

    /**
     * Resolve the views directory.
     */
    private function resolveViewDirectory(): ?string
    {
        $projectRoot = $this->console->findComposerRoot();
        if ($projectRoot === null) {
            return null;
        }

        foreach (['views', 'resources/views', 'app/views'] as $rel) {
            $abs = $projectRoot . '/' . $rel;
            if (is_dir($abs)) {
                return $abs;
            }
        }

        // Create views/ as last resort
        $abs = $projectRoot . '/views';
        $this->ensureDirectory($abs);
        return $abs;
    }

    /**
     * Detect the root application namespace (e.g. "App") from composer.json PSR-4.
     */
    private function detectRootNamespace(): string
    {
        $map = $this->console->readComposerPsr4();
        foreach ($map as $ns => $dir) {
            // Look for "App" or the first namespace that maps to app/ or src/
            $base = basename(rtrim($dir, '/\\'));
            if (strtoupper($ns) === 'APP' || in_array($base, ['app', 'src', 'App', 'Src'], true)) {
                return rtrim($ns, '\\');
            }
        }

        // Fallback: first namespace in the map
        return !empty($map) ? rtrim(array_key_first($map), '\\') : 'App';
    }

    // -------------------------------------------------------------------------
    //  File writing
    // -------------------------------------------------------------------------

    private function writeScaffold(string $filePath, string $content, string $label): void
    {
        if (is_file($filePath) && !$this->option('force', false)) {
            $this->warn("File already exists: {$filePath}");
            $this->muted('  Use --force to overwrite.');
            return;
        }

        $this->ensureDirectory(dirname($filePath));
        file_put_contents($filePath, $content);

        $relDisplay = $this->relativePath($filePath);
        $this->success("Created {$relDisplay}");
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Return a display-friendly relative path from the project root.
     * @param string $absolutePath
     */
    private function relativePath(string $absolutePath): string
    {
        $root = $this->console->findComposerRoot();
        if ($root !== null && str_starts_with($absolutePath, $root)) {
            return substr($absolutePath, strlen($root) + 1);
        }
        return $absolutePath;
    }

    // -------------------------------------------------------------------------
    //  Name normalization
    // -------------------------------------------------------------------------

    /**
     * Normalize a user-provided name into a PascalCase class name.
     * Strips suffixes that would be redundant (e.g. "UserController" → "User" when $suffix is "Controller").
     * @param string $name
     * @param string $suffix
     */
    private function normalizeClassName(string $name, string $suffix): string
    {
        // Strip trailing suffix if already provided
        if ($suffix !== '' && str_ends_with($name, $suffix)) {
            $name = substr($name, 0, -strlen($suffix));
        }

        // Convert from various formats to PascalCase
        $name = str_replace(['-', '_', '.'], ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '', $name);

        // Re-add suffix
        return $name . $suffix;
    }

    /**
     * Convert a PascalCase class name to a snake_case table name.
     * @param string $className
     */
    private function deriveTableName(string $className): string
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
        // Simple pluralization: just add 's'
        return $snake . 's';
    }
}