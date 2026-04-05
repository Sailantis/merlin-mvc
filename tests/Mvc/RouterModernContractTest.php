<?php
namespace Merlin\Tests\Mvc;

require_once __DIR__ . '/../../vendor/autoload.php';

use Merlin\Mvc\Router;
use PHPUnit\Framework\TestCase;

class RouterModernContractTest extends TestCase
{
    public function testNamedWildcardIsCapturedWithoutSpecialName(): void
    {
        $router = new Router();
        $router->add('GET', '/{controller}/{action}/{args:*}', null);

        $result = $router->match('/user/view/foo/bar');

        $this->assertNotNull($result);
        $this->assertEquals('user', $result['vars']['controller']);
        $this->assertEquals('view', $result['vars']['action']);
        $this->assertEquals(['foo', 'bar'], $result['vars']['args']);

        $this->assertArrayNotHasKey('namespace', $result);
        $this->assertArrayNotHasKey('controller', $result);
        $this->assertArrayNotHasKey('action', $result);
        $this->assertArrayNotHasKey('params', $result);
    }

    public function testInlinePrefixAndMiddlewareApplyToSubsequentRoutesAndScopedGroupsRestoreState(): void
    {
        $router = (new Router())
            ->middleware('auth')
            ->prefix('/admin');

        $router->prefix('/reports', function (Router $router): void {
            $router->middleware('audit');
            $router->add('GET', '/dashboard', 'Admin::dashboard');
        });

        $router->add('GET', '/profile', 'Account::profile');

        $dashboard = $router->match('/admin/reports/dashboard');
        $profile = $router->match('/admin/profile');

        $this->assertNotNull($dashboard);
        $this->assertSame(['auth', 'audit'], $dashboard['groups']);

        $this->assertNotNull($profile);
        $this->assertSame(['auth'], $profile['groups']);
    }

    public function testInlineNamespaceAppliesAndScopedNamespaceRestoresState(): void
    {
        $router = (new Router())->namespace('Admin');

        $router->namespace('Api', function (Router $router): void {
            $router->add('GET', '/users', 'UserController::list');
        });

        $router->add('GET', '/dashboard', 'DashboardController::view');

        $users = $router->match('/users');
        $dashboard = $router->match('/dashboard');

        $this->assertNotNull($users);
        $this->assertSame(
            ['namespace' => 'Admin\\Api', 'controller' => 'UserController', 'action' => 'list'],
            $users['override']
        );

        $this->assertNotNull($dashboard);
        $this->assertSame(
            ['namespace' => 'Admin', 'controller' => 'DashboardController', 'action' => 'view'],
            $dashboard['override']
        );
    }

    public function testNamespaceOnlyRouteKeepsRelativeNamespaceOverride(): void
    {
        $router = new Router();

        $router->prefix('/phpthunder', function (Router $router): void {
            $router->namespace('PhpThunder');
            $router->add('GET', '/');
        });

        $result = $router->match('/phpthunder');

        $this->assertNotNull($result);
        $this->assertSame(['namespace' => 'PhpThunder'], $result['override']);
    }

    public function testInlineControllerAppliesToSubsequentRoutes(): void
    {
        $router = (new Router())->controller('UserController');

        $router->add('GET', '/profile', '::show');

        $result = $router->match('/profile');

        $this->assertNotNull($result);
        $this->assertSame(
            ['controller' => 'UserController', 'action' => 'show'],
            $result['override']
        );
    }

    public function testOptionalTypedSegmentMatchesWithAndWithoutValue(): void
    {
        $router = new Router();
        $router->add('GET', '/users/{id?:int}', null);

        $withoutId = $router->match('/users');
        $withId = $router->match('/users/42');

        $this->assertNotNull($withoutId);
        $this->assertArrayNotHasKey('id', $withoutId['vars']);

        $this->assertNotNull($withId);
        $this->assertSame('42', $withId['vars']['id']);
    }

    public function testOptionalTypedSegmentRejectsInvalidValue(): void
    {
        $router = new Router();
        $router->add('GET', '/users/{id?:int}', null);

        $result = $router->match('/users/abc');

        $this->assertNull($result);
    }
}
