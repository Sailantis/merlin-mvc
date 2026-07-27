<?php
namespace Azera\Tests\Mvc;

use Azera\Core\Router;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';


class RouterTest extends TestCase
{
    public function testBasicParametricRoute(): void
    {
        $router = new Router();
        $router->add('GET', '/user/{id}', 'UserController::viewAction');

        $result = $router->match('/user/123');

        $this->assertNotNull($result);
        $this->assertEquals(['id' => 123], $result['vars']);
        $this->assertEquals(['controller' => 'UserController', 'action' => 'viewAction'], $result['override']);
    }

    public function testMultipleParams(): void
    {
        $router = new Router();
        $router->add('GET', '/a/{x}/b/{y}/c/{z}', 'Test::multi');

        $result = $router->match('/a/1/b/2/c/3');

        $this->assertNotNull($result);
        $this->assertEquals(['x' => '1', 'y' => '2', 'z' => '3'], $result['vars']);
    }

    public function testIntTypeConstraintMatches(): void
    {
        $router = new Router();
        $router->add('GET', '/user/{id:int}', 'User::view');

        $result = $router->match('/user/123');

        $this->assertNotNull($result);
        $this->assertEquals(['id' => 123], $result['vars']);
    }

    public function testIntTypeConstraintRejects(): void
    {
        $router = new Router();
        $router->add('GET', '/user/{id:int}', 'User::view');

        $result = $router->match('/user/abc');

        $this->assertNull($result);
    }

    public function testWildcardParams(): void
    {
        $router = new Router();
        $router->add('GET', '/files/{params:*}', 'Files::serve');

        $result = $router->match('/files/2024/documents/report.pdf');

        $this->assertNotNull($result);
        $this->assertEquals([0 => '2024', 1 => 'documents', 2 => 'report.pdf'], $result['vars']['params']);
    }

    public function testReverseRoutingWithNamedParams(): void
    {
        $router = new Router();
        $router->add('GET', '/user/{id:int}/post/{slug}', 'User::viewPost')->setName('user.post.view');

        $url = $router->urlFor('user.post.view', ['id' => 42, 'slug' => 'hello-world']);

        $this->assertEquals('/user/42/post/hello-world', $url);
    }

    public function testRouteGroupsAreReturnedInResult(): void
    {
        $router = new Router();

        $router->middleware('auth', function ($r) {
            $r->prefix('/admin', function ($r) {
                $r->add('GET', '/dashboard', 'Admin::dashboard');
            });
        });

        $result = $router->match('/admin/dashboard');

        $this->assertNotNull($result);
        $this->assertEquals(['auth'], $result['groups']);
    }

    public function testMatchReturnsSeparatedVarsAndOverrideForNamedDynamicPattern(): void
    {
        $router = new Router();
        $router->add('GET', '/{id:int}/{controller}/{action}/{params:*}', [
            'controller' => 'test',
            'action' => 'test',
        ]);

        $result = $router->match('/123/user/view/foo/bar');

        $this->assertNotNull($result);
        $this->assertEquals(123, $result['vars']['id']);
        $this->assertEquals('user', $result['vars']['controller']);
        $this->assertEquals('view', $result['vars']['action']);
        $this->assertEquals(['foo', 'bar'], $result['vars']['params']);
        $this->assertEquals(['controller' => 'test', 'action' => 'test'], $result['override']);
    }
}
