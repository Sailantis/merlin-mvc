<?php

namespace Azera\Tests\Security;

require_once __DIR__ . '/../../vendor/autoload.php';

use Azera\AppContext;
use Azera\Http\Request;
use Azera\Http\Response;
use Azera\Http\Session;
use Azera\Security\CsrfMiddleware;
use PHPUnit\Framework\TestCase;

class CsrfMiddlewareTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $sessionStore = [];

    protected function setUp(): void
    {
        AppContext::setInstance(new AppContext());
        $this->sessionStore = [];
    }

    private function context(array $server = [], array $post = []): AppContext
    {
        $ctx = AppContext::instance();
        $ctx->set(Request::class, new Request($server, [], $post));
        $ctx->setSession(new Session($this->sessionStore));

        return $ctx;
    }

    public function testGetRequestsPassThrough(): void
    {
        $ctx    = $this->context(['REQUEST_METHOD' => 'GET']);
        $called = false;
        $result = (new CsrfMiddleware())->process($ctx, function () use (&$called) {
            $called = true;
            return null;
        });

        $this->assertTrue($called);
        $this->assertNull($result);
    }

    public function testPostWithoutTokenIsDenied(): void
    {
        $ctx    = $this->context(['REQUEST_METHOD' => 'POST']);
        $called = false;
        $result = (new CsrfMiddleware())->process($ctx, function () use (&$called) {
            $called = true;
            return null;
        });

        $this->assertFalse($called);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(419, $result->getStatus());
    }

    public function testPostWithValidTokenPasses(): void
    {
        $middleware = new CsrfMiddleware();

        // First, generate the token via a GET request.
        $ctx = $this->context(['REQUEST_METHOD' => 'GET']);
        $middleware->process($ctx, fn() => null);
        $token = $this->sessionStore[CsrfMiddleware::SESSION_KEY];

        // Now submit a POST with the token in the body.
        $ctx = $this->context(
            ['REQUEST_METHOD' => 'POST'],
            [CsrfMiddleware::TOKEN_NAME => $token],
        );
        // Reuse the same session store so the token matches.
        AppContext::instance()->setSession(new Session($this->sessionStore));

        $called = false;
        $result = $middleware->process($ctx, function () use (&$called) {
            $called = true;
            return null;
        });

        $this->assertTrue($called);
        $this->assertNull($result);
    }

    public function testPostWithInvalidTokenIsDenied(): void
    {
        $ctx = $this->context(
            ['REQUEST_METHOD' => 'POST'],
            [CsrfMiddleware::TOKEN_NAME => 'wrong-token'],
        );
        // Seed a session token so the comparison is meaningful.
        $this->sessionStore[CsrfMiddleware::SESSION_KEY] = 'real-token';
        AppContext::instance()->setSession(new Session($this->sessionStore));

        $called = false;
        $result = (new CsrfMiddleware())->process($ctx, function () use (&$called) {
            $called = true;
            return null;
        });

        $this->assertFalse($called);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(419, $result->getStatus());
    }

    public function testTokenIsGeneratedLazily(): void
    {
        $this->assertArrayNotHasKey(CsrfMiddleware::SESSION_KEY, $this->sessionStore);

        $middleware = new CsrfMiddleware();
        $ctx        = $this->context(['REQUEST_METHOD' => 'GET']);
        $middleware->process($ctx, fn() => null);

        $this->assertArrayHasKey(CsrfMiddleware::SESSION_KEY, $this->sessionStore);
        $this->assertIsString($this->sessionStore[CsrfMiddleware::SESSION_KEY]);
        $this->assertNotSame('', $this->sessionStore[CsrfMiddleware::SESSION_KEY]);
    }

    public function testTokenIsReusableAcrossRequests(): void
    {
        $middleware = new CsrfMiddleware();

        $ctx = $this->context(['REQUEST_METHOD' => 'GET']);
        $middleware->process($ctx, fn() => null);
        $first = $this->sessionStore[CsrfMiddleware::SESSION_KEY];

        $ctx = $this->context(['REQUEST_METHOD' => 'GET']);
        $middleware->process($ctx, fn() => null);
        $second = $this->sessionStore[CsrfMiddleware::SESSION_KEY];

        $this->assertSame($first, $second);
    }

    public function testTokenHeaderIsAccepted(): void
    {
        $middleware = new CsrfMiddleware();

        $ctx = $this->context(['REQUEST_METHOD' => 'GET']);
        $middleware->process($ctx, fn() => null);
        $token = $this->sessionStore[CsrfMiddleware::SESSION_KEY];

        $ctx = $this->context([
            'REQUEST_METHOD'    => 'POST',
            'HTTP_X_CSRF_TOKEN' => $token,
        ]);
        AppContext::instance()->setSession(new Session($this->sessionStore));

        $called = false;
        $result = $middleware->process($ctx, function () use (&$called) {
            $called = true;
            return null;
        });

        $this->assertTrue($called);
        $this->assertNull($result);
    }

    public function testDeleteRequestsAreGuarded(): void
    {
        $ctx    = $this->context(['REQUEST_METHOD' => 'DELETE']);
        $called = false;
        $result = (new CsrfMiddleware())->process($ctx, function () use (&$called) {
            $called = true;
            return null;
        });

        $this->assertFalse($called);
        $this->assertSame(419, $result->getStatus());
    }

    public function testCustomGuardedMethods(): void
    {
        $middleware = new CsrfMiddleware(guardedMethods: ['POST']);

        // GET passes.
        $ctx    = $this->context(['REQUEST_METHOD' => 'GET']);
        $called = false;
        $middleware->process($ctx, function () use (&$called) {
            $called = true;
            return null;
        });
        $this->assertTrue($called);

        // DELETE passes because only POST is guarded.
        $ctx    = $this->context(['REQUEST_METHOD' => 'DELETE']);
        $called = false;
        $middleware->process($ctx, function () use (&$called) {
            $called = true;
            return null;
        });
        $this->assertTrue($called);
    }

    public function testWithoutSessionPostIsDenied(): void
    {
        $ctx = new AppContext();
        AppContext::setInstance($ctx);
        $ctx->set(Request::class, new Request(['REQUEST_METHOD' => 'POST']));

        $result = (new CsrfMiddleware())->process($ctx, fn() => null);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(419, $result->getStatus());
    }

    public function testWithoutSessionGetPassesThrough(): void
    {
        $ctx = new AppContext();
        AppContext::setInstance($ctx);
        $ctx->set(Request::class, new Request(['REQUEST_METHOD' => 'GET']));

        $called = false;
        $result = (new CsrfMiddleware())->process($ctx, function () use (&$called) {
            $called = true;
            return null;
        });

        $this->assertTrue($called);
        $this->assertNull($result);
    }
}