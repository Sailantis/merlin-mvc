<?php
namespace Merlin\Http;

use Merlin\AppContext;
use Merlin\Http\Response;
use Merlin\Core\MiddlewareInterface;

/**
 * Middleware to manage PHP sessions.
 * 
 * This middleware ensures that a session is started for each request and 
 * provides access to session data through the AppContext. It also ensures
 * that session data is properly saved at the end of the request before the
 * response is sent.
 */
class SessionMiddleware implements MiddlewareInterface
{
    /**
     * Start the PHP session, expose it through {@see AppContext::session()},
     * invoke the next middleware, then flush the session to storage.
     *
     * @param AppContext $context Application context.
     * @param callable   $next    Next middleware callable.
     * @return \Merlin\Http\Response|null The response from the downstream pipeline.
     */
    public function process(AppContext $context, callable $next): ?Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $context->setSession(new Session($_SESSION));

        $response = $next();

        session_write_close();

        return $response;
    }
}
