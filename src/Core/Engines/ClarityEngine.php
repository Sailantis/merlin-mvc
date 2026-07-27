<?php
namespace Azera\Core\Engines;

use Clarity\ClarityEngineTrait;
use Azera\Core\ViewEngine;

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
    use ClarityEngineTrait;

    protected string $extension = '.clarity.html';

    public function __construct(array $vars = [])
    {
        $this->vars = $vars;
        $this->initializeClarityEngine();
    }

}
