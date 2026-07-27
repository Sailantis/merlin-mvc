<?php
namespace Azera\Boot;

interface BootstrapProvider
{
    /**
     * Boot the application.
     *
     * The provider obtains the correct AppContext (or a subclass)
     * via its own AppContext::instance() call.
     */
    public function boot(): void;
}