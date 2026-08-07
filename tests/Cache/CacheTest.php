<?php

namespace Azera\Tests\Cache;

use PHPUnit\Framework\TestCase;
use Azera\Cache\NullCache;
use Azera\Cache\ArrayCache;
use Psr\SimpleCache\CacheInterface;
use Azera\Cache\InvalidArgumentException;

class NullCacheTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(CacheInterface::class, new NullCache());
    }

    public function testGetReturnsDefault(): void
    {
        $cache = new NullCache();
        $this->assertNull($cache->get('key'));
        $this->assertSame('fallback', $cache->get('key', 'fallback'));
    }

    public function testHasReturnsFalse(): void
    {
        $this->assertFalse((new NullCache())->has('key'));
    }

    public function testSetReturnsTrue(): void
    {
        $this->assertTrue((new NullCache())->set('key', 'value'));
    }

    public function testDeleteReturnsTrue(): void
    {
        $this->assertTrue((new NullCache())->delete('key'));
    }

    public function testClearReturnsTrue(): void
    {
        $this->assertTrue((new NullCache())->clear());
    }

    public function testGetMultipleReturnsDefaults(): void
    {
        $cache  = new NullCache();
        $result = $cache->getMultiple(['a', 'b'], 'def');

        $expected = ['a' => 'def', 'b' => 'def'];
        $this->assertSame($expected, iterator_to_array($result));
    }
}

class ArrayCacheTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(CacheInterface::class, new ArrayCache());
    }

    public function testSetAndGet(): void
    {
        $cache = new ArrayCache();
        $cache->set('name', 'Azera');
        $this->assertSame('Azera', $cache->get('name'));
    }

    public function testGetReturnsDefaultOnMiss(): void
    {
        $cache = new ArrayCache();
        $this->assertNull($cache->get('missing'));
        $this->assertSame('default', $cache->get('missing', 'default'));
    }

    public function testHas(): void
    {
        $cache = new ArrayCache();
        $cache->set('key', 'value');
        $this->assertTrue($cache->has('key'));
        $this->assertFalse($cache->has('missing'));
    }

    public function testDelete(): void
    {
        $cache = new ArrayCache();
        $cache->set('key', 'value');
        $cache->delete('key');
        $this->assertFalse($cache->has('key'));
    }

    public function testClear(): void
    {
        $cache = new ArrayCache();
        $cache->set('a', 1);
        $cache->set('b', 2);
        $cache->clear();
        $this->assertFalse($cache->has('a'));
        $this->assertFalse($cache->has('b'));
    }

    public function testTtlExpires(): void
    {
        $cache = new ArrayCache();
        $cache->set('temp', 'value', 1);
        $this->assertTrue($cache->has('temp'));

        // Simulate time passing by manipulating the entry directly
        $ref      = new \ReflectionClass($cache);
        $dataProp = $ref->getProperty('data');
        $data     = $dataProp->getValue($cache);
        $data['temp']['expires'] = 0; // already expired
        $dataProp->setValue($cache, $data);

        $this->assertFalse($cache->has('temp'));
        $this->assertNull($cache->get('temp'));
    }

    public function testNullTtlMeansNoExpiry(): void
    {
        $cache = new ArrayCache();
        $cache->set('perm', 'value');
        $this->assertTrue($cache->has('perm'));
    }

    public function testSetMultipleAndGetMultiple(): void
    {
        $cache = new ArrayCache();
        $cache->setMultiple(['a' => 1, 'b' => 2, 'c' => 3]);

        $result = $cache->getMultiple(['a', 'b', 'c']);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], iterator_to_array($result));
    }

    public function testDeleteMultiple(): void
    {
        $cache = new ArrayCache();
        $cache->setMultiple(['a' => 1, 'b' => 2, 'c' => 3]);
        $cache->deleteMultiple(['a', 'c']);

        $this->assertFalse($cache->has('a'));
        $this->assertTrue($cache->has('b'));
        $this->assertFalse($cache->has('c'));
    }

    public function testInvalidKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ArrayCache())->get('');
    }

    public function testKeyTooLongThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ArrayCache())->get(str_repeat('a', 65));
    }

    public function testKeyWithSpacesThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ArrayCache())->get('key with spaces');
    }

    public function testValidKeyWithDotsAndDashes(): void
    {
        $cache = new ArrayCache();
        $cache->set('user.123-session_id', 'value');
        $this->assertSame('value', $cache->get('user.123-session_id'));
    }
}