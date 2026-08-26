<?php

declare(strict_types=1);

namespace Azera\Lifecycle;

/**
 * Opt-in contract for services that hold per-request state.
 *
 * Traditional PHP-FPM runs one request per process, so anything request-scoped
 * is naturally destroyed when the process exits. Long-lived application
 * servers (RoadRunner, Swoole, FrankenPHP, Laravel Octane, …) keep workers —
 * and the shared {@see \Azera\AppContext} singleton — alive across many
 * requests. Anything that would normally be re-created per request must
 * therefore be reset between requests to avoid leaking state from one request
 * into the next.
 *
 * Implement {@see RequestScoped} on a service, register it on the context, and
 * it is flushed automatically whenever {@see \Azera\AppContext::clearRequestScope()}
 * is called (after each request in a persistent worker).
 *
 * Infrastructure services that own persistent connections (database manager,
 * cache/Redis backends, queue, logger, event dispatcher) can stay resident
 * across requests and should NOT implement this interface.
 */
interface RequestScoped
{
    /**
     * Reset all per-request state held by this service.
     *
     * Called once after the worker finishes processing a request, before the
     * next one is read. The service may keep its persistent handles and
     * connections, but must clear any data that belongs to the request that
     * has just finished.
     */
    public function resetState(): void;
}