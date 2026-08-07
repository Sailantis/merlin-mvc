<?php

namespace Azera\Tests\Config;

use PHPUnit\Framework\TestCase;
use Azera\Config\Config;

class ConfigTest extends TestCase
{
    public function testGetReturnsValue(): void
    {
        $config = new Config(['db' => ['dsn' => 'mysql:host=localhost', 'user' => 'root']]);
        $this->assertSame('mysql:host=localhost', $config->get('db.dsn'));
        $this->assertSame('root', $config->get('db.user'));
    }

    public function testGetReturnsDefaultOnMiss(): void
    {
        $config = new Config(['db' => ['dsn' => 'mysql']]);
        $this->assertNull($config->get('db.host'));
        $this->assertSame('localhost', $config->get('db.host', 'localhost'));
        $this->assertNull($config->get('missing.key'));
    }

    public function testHas(): void
    {
        $config = new Config(['db' => ['dsn' => 'mysql']]);
        $this->assertTrue($config->has('db.dsn'));
        $this->assertFalse($config->has('db.host'));
        $this->assertFalse($config->has('missing'));
    }

    public function testSet(): void
    {
        $config = new Config([]);
        $config->set('db.dsn', 'mysql:host=localhost');
        $this->assertSame('mysql:host=localhost', $config->get('db.dsn'));
    }

    public function testSetOverwritesExisting(): void
    {
        $config = new Config(['app' => ['name' => 'old']]);
        $config->set('app.name', 'new');
        $this->assertSame('new', $config->get('app.name'));
    }

    public function testSetCreatesNestedPath(): void
    {
        $config = new Config([]);
        $config->set('deep.nested.key', 'value');
        $this->assertSame('value', $config->get('deep.nested.key'));
    }

    public function testAllReturnsFullArray(): void
    {
        $data   = ['a' => 1, 'b' => ['c' => 2]];
        $config = new Config($data);
        $this->assertSame($data, $config->all());
    }

    public function testSetArrayReplacesData(): void
    {
        $config = new Config(['old' => 1]);
        $config->setArray(['new' => 2]);
        $this->assertNull($config->get('old'));
        $this->assertSame(2, $config->get('new'));
    }

    public function testMergeRecursive(): void
    {
        $config = new Config(['db' => ['dsn' => 'mysql', 'user' => 'root']]);
        $config->merge(['db' => ['dsn' => 'pgsql', 'pass' => 'secret']]);
        $this->assertSame('pgsql', $config->get('db.dsn'));
        $this->assertSame('root', $config->get('db.user'));
        $this->assertSame('secret', $config->get('db.pass'));
    }

    public function testScopeReturnsSubConfig(): void
    {
        $config = new Config(['db' => ['dsn' => 'mysql', 'user' => 'root']]);
        $scope  = $config->scope('db');
        $this->assertSame('mysql', $scope->get('dsn'));
        $this->assertSame('root', $scope->get('user'));
    }

    public function testScopeOnMissingReturnsEmpty(): void
    {
        $config = new Config([]);
        $scope  = $config->scope('missing');
        $this->assertNull($scope->get('anything'));
    }
}