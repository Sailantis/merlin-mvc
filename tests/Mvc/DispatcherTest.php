<?php
namespace Merlin\Tests\Mvc;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/PhpThunder/AuthController.php';

use Merlin\AppContext;
use Merlin\Http\Session;
use Merlin\Http\Response;
use Merlin\Mvc\Controller;
use Merlin\Mvc\Dispatcher;
use Merlin\Mvc\MiddlewareInterface;
use Merlin\Mvc\Router;
use PHPUnit\Framework\TestCase;
// --- Helper controllers / middleware (top-level declarations) ---

class DTResponseController extends Controller
{
    public function act()
    {
        return Response::text('response');
    }
}
class DTStringController extends Controller
{
    public function act()
    {
        return 'hello';
    }
}
class DTArrayController extends Controller
{
    public function act()
    {
        return ['a' => 1];
    }
}
class DTIntController extends Controller
{
    public function act()
    {
        return 201;
    }
}
class DTNullController extends Controller
{
    public function act()
    {
        return null;
    }
}

class BeforeController extends Controller
{
    public $called = false;
    public function beforeAction(?string $action = null, array $params = []): ?Response
    {
        return Response::text('before');
    }
    public function act()
    {
        $this->called = true;
        return 'should-not';
    }
}

class GMW implements MiddlewareInterface
{
    public function process(AppContext $context, callable $next): ?Response
    {
        $r = $next();
        if ($r instanceof Response)
            $r->write('-G');
        return $r;
    }
}
class GRPMW implements MiddlewareInterface
{
    public function process(AppContext $context, callable $next): ?Response
    {
        $r = $next();
        if ($r instanceof Response)
            $r->write('-GR');
        return $r;
    }
}
class CMW implements MiddlewareInterface
{
    public function process(AppContext $context, callable $next): ?Response
    {
        $r = $next();
        if ($r instanceof Response)
            $r->write('-C');
        return $r;
    }
}
class AMW implements MiddlewareInterface
{
    public function process(AppContext $context, callable $next): ?Response
    {
        $r = $next();
        if ($r instanceof Response)
            $r->write('-A');
        return $r;
    }
}

class MWController extends Controller
{
    protected array $middleware = [CMW::class];
    protected array $actionMiddleware = ['act' => [AMW::class]];
    public function act()
    {
        return 'CORE';
    }
}

class DynamicTargetController extends Controller
{
    public function sampleAction()
    {
        return 'dynamic';
    }
}

class NullableSessionActionController extends Controller
{
    public function act(?Session $session = null)
    {
        return $session === null ? 'no-session' : 'has-session';
    }
}

class RoutingStateController extends Controller
{
    public function fromOverride($id = null, $args = null, ...$params)
    {
        $route = $this->context()->route();
        return [
            'controller' => $route->controller ?? null,
            'action' => $route->action ?? null,
            'groups' => $route->groups ?? null,
            'vars' => $route->vars ?? null,
            'params' => $route->params ?? null,
        ];
    }
}

class DispatcherTest extends TestCase
{
    private function routeWithOverride(string $controllerClass, string $action, array $params = [], array $groups = []): array
    {
        $pos = strrpos($controllerClass, '\\');
        $namespace = $pos === false ? '' : '\\' . substr($controllerClass, 0, $pos);
        $controller = $pos === false ? $controllerClass : substr($controllerClass, $pos + 1);

        return [
            'vars' => [
                'params' => $params,
            ],
            'override' => [
                'namespace' => $namespace,
                'controller' => $controller,
                'action' => $action,
            ],
            'groups' => $groups,
        ];
    }

    private function responseBody(Response $r): string
    {
        $rp = new \ReflectionProperty($r, 'body');
        $rp->setAccessible(true);
        return $rp->getValue($r);
    }

    private function responseStatus(Response $r): int
    {
        $rp = new \ReflectionProperty($r, 'status');
        $rp->setAccessible(true);
        return $rp->getValue($r);
    }

    private function responseHeaders(Response $r): array
    {
        $rp = new \ReflectionProperty($r, 'headers');
        $rp->setAccessible(true);
        return $rp->getValue($r);
    }

    public function testReturnTypeConversions(): void
    {
        $disp = new Dispatcher();

        // response passthrough
        $res = $disp->dispatch($this->routeWithOverride(DTResponseController::class, 'act'));
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals('response', $this->responseBody($res));
        $this->assertEquals('text/plain; charset=utf-8', $this->responseHeaders($res)['Content-Type']);

        // string -> text
        $res = $disp->dispatch($this->routeWithOverride(DTStringController::class, 'act'));
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals('hello', $this->responseBody($res));
        $this->assertEquals('text/html; charset=utf-8', $this->responseHeaders($res)['Content-Type']);

        // array -> json
        $res = $disp->dispatch($this->routeWithOverride(DTArrayController::class, 'act'));
        $this->assertEquals('application/json', $this->responseHeaders($res)['Content-Type']);
        $this->assertEquals(json_encode(['a' => 1], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $this->responseBody($res));

        // int -> status
        $res = $disp->dispatch($this->routeWithOverride(DTIntController::class, 'act'));
        $this->assertEquals(201, $this->responseStatus($res));

        // null -> 204
        $res = $disp->dispatch($this->routeWithOverride(DTNullController::class, 'act'));
        $this->assertEquals(204, $this->responseStatus($res));
    }

    public function testBeforeAndAfterActionShortCircuit(): void
    {
        $disp = new Dispatcher();

        $res = $disp->dispatch($this->routeWithOverride(BeforeController::class, 'act'));
        $this->assertEquals('before', $this->responseBody($res));
    }

    public function testMiddlewarePipelineOrder(): void
    {
        $disp = new Dispatcher();

        $disp->addMiddleware(new GMW());
        $disp->defineMiddlewareGroup('grp', [GRPMW::class]);

        $res = $disp->dispatch($this->routeWithOverride(MWController::class, 'act', [], ['grp']));
        $this->assertEquals('CORE-A-C-GR-G', $this->responseBody($res));
    }

    public function testNamespaceOnlyRouteFallsBackToDefaultControllerAndAction(): void
    {
        $context = new AppContext();
        AppContext::setInstance($context);

        $router = new Router();
        $router->prefix('/phpthunder', function (Router $router): void {
            $router->namespace('PhpThunder');
            $router->add('GET', '/');
        });

        $route = $router->match('/phpthunder');

        $this->assertNotNull($route);
        $this->assertSame(['namespace' => 'PhpThunder'], $route['override']);

        $disp = new Dispatcher();
        $disp->setBaseNamespace('\\Merlin\\Tests\\Mvc');

        $res = $disp->dispatch($route);

        $this->assertEquals('php-thunder-home', $this->responseBody($res));
    }

    public function testScopedControllerInsideNamespaceResolvesToRequestedController(): void
    {
        $context = new AppContext();
        AppContext::setInstance($context);

        $router = new Router();
        $router->prefix('/phpthunder', function (Router $router): void {
            $router->namespace('PhpThunder');
            $router->controller('AuthController', function (Router $router): void {
                $router->add(['GET', 'POST'], '/login', '::loginAction');
                $router->add('GET', '/logout', '::logoutAction');
            });
        });

        $route = $router->match('/phpthunder/login', 'POST');

        $this->assertNotNull($route);
        $this->assertSame([
            'namespace' => 'PhpThunder',
            'controller' => 'AuthController',
            'action' => 'loginAction',
        ], $route['override']);

        $disp = new Dispatcher();
        $disp->setBaseNamespace('\\Merlin\\Tests\\Mvc');

        $res = $disp->dispatch($route);

        $this->assertEquals('auth-login', $this->responseBody($res));
    }

    public function testDynamicVarsAddSuffixesButOverrideDoesNot(): void
    {
        $disp = new Dispatcher();
        $disp->setBaseNamespace('\\Merlin\\Tests\\Mvc');

        $res = $disp->dispatch([
            'vars' => [
                'controller' => 'dynamic-target',
                'action' => 'sample',
                'params' => [],
            ],
            'override' => [],
            'groups' => [],
        ]);

        $this->assertEquals('dynamic', $this->responseBody($res));

        $res = $disp->dispatch([
            'vars' => [
                'controller' => 'ignored-dynamic',
                'action' => 'ignored',
            ],
            'override' => [
                'controller' => 'RoutingStateController',
                'action' => 'fromOverride',
                'namespace' => '\\Merlin\\Tests\\Mvc',
            ],
            'groups' => [],
        ]);

        $this->assertEquals('application/json', $this->responseHeaders($res)['Content-Type']);
        $payload = json_decode($this->responseBody($res), true);
        $this->assertEquals('\\Merlin\\Tests\\Mvc\\RoutingStateController', $payload['controller']);
        $this->assertEquals('fromOverride', $payload['action']);
    }

    public function testDispatcherStoresNormalizedRoutingInContext(): void
    {
        $context = new AppContext();
        AppContext::setInstance($context); // Ensure singleton instance is used
        $disp = new Dispatcher();
        $disp->setBaseNamespace('\\Merlin\\Tests\\Mvc');

        $res = $disp->dispatch([
            'vars' => [
                'id' => 42,
                'controller' => 'routing-state',
                'action' => 'from-override',
                'args' => ['x', 'y'],
            ],
            'override' => [
                'controller' => 'RoutingStateController',
                'action' => 'fromOverride',
                'namespace' => '\\Merlin\\Tests\\Mvc',
            ],
        ]);

        $this->assertEquals('application/json', $this->responseHeaders($res)['Content-Type']);
        $payload = json_decode($this->responseBody($res), true);
        $this->assertEquals([], $payload['groups']);
        $this->assertEquals(42, $payload['vars']['id']);

        $stored = $context->route();
        $this->assertNotNull($stored);
        $this->assertSame([], $stored->groups);
        $this->assertSame('\\Merlin\\Tests\\Mvc\\RoutingStateController', $stored->controller);
        $this->assertSame('fromOverride', $stored->action);
        $this->assertSame(['x', 'y'], $stored->vars['args']);
        $this->assertSame([42, ['x', 'y']], $stored->params);
    }

    public function testOnlyParamsWildcardIsPassedToActionParameters(): void
    {
        $context = new AppContext();
        AppContext::setInstance($context); // Ensure singleton instance is used
        $disp = new Dispatcher();

        $res = $disp->dispatch([
            'vars' => [
                'id' => 42,
                'controller' => 'routing-state',
                'action' => 'from-override',
                'params' => ['x', 'y'],
            ],
            'override' => [
                'controller' => 'RoutingStateController',
                'action' => 'fromOverride',
                'namespace' => '\\Merlin\\Tests\\Mvc',
            ],
        ]);

        $this->assertEquals('application/json', $this->responseHeaders($res)['Content-Type']);

        $stored = $context->route();
        $this->assertNotNull($stored);
        $this->assertSame([42, null, 'x', 'y'], $stored->params);
        $this->assertSame(['x', 'y'], $stored->vars['params']);
    }

    public function testNullableRegisteredServiceFallsBackToNullInActionInjection(): void
    {
        $context = new AppContext();
        AppContext::setInstance($context);
        $disp = new Dispatcher();

        $res = $disp->dispatch($this->routeWithOverride(NullableSessionActionController::class, 'act'));

        $this->assertEquals('no-session', $this->responseBody($res));
    }

    /** @dataProvider reservedActionProvider */
    public function testReservedActionsCannotBeDispatched(string $reservedAction): void
    {
        $context = new AppContext();
        AppContext::setInstance($context);
        $disp = new Dispatcher();

        $this->expectException(\Merlin\Mvc\Exceptions\ActionNotFoundException::class);

        $disp->dispatch($this->routeWithOverride(
            DTResponseController::class,
            $reservedAction
        ));
    }

    public static function reservedActionProvider(): array
    {
        return [
            'beforeAction' => ['beforeAction'],
            'afterAction' => ['afterAction'],
        ];
    }
}