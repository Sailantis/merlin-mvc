<?php

namespace Merlin\Core;

use Merlin\AppContext;
use Merlin\Http\Response;

/**
 * Contract for all middleware classes in the Merlin pipeline.
 *
 * Implementations receive the application context and a callable representing
 * the remainder of the pipeline. They can short-circuit processing by returning
 * a {@see Response} directly, or continue by calling {@see $next()} and
 * optionally modifying its result.
 */
interface MiddlewareInterface
{
    /**
     * Process the incoming request and optionally delegate to the next handler.
     *
     * @param AppContext $context Application context for the current request.
     * @param callable   $next    Callable that invokes the remaining pipeline. Returns ?Response.
     * @return Response|null Response to send, or null to continue (caller resumes the pipeline).
     */
    public function process(AppContext $context, callable $next): ?Response;
}
