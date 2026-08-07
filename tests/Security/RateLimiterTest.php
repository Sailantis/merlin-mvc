<?php

namespace Azera\Tests\Security;

require_once __DIR__ . '/../../vendor/autoload.php';

use Azera\Cache\ArrayCache;
use Azera\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    private function limiter(): array
    {
        $cache   = new ArrayCache();
        $limiter = new RateLimiter($cache);

        return [$limiter, $cache];
    }

    public function testAllowsWithinLimit(): void
    {
        [$limiter] = $this->limiter();

        $this->assertTrue($limiter->limit('ip:127.0.0.1', 5, 60));
        $this->assertTrue($limiter->limit('ip:127.0.0.1', 5, 60));
        $this->assertTrue($limiter->limit('ip:127.0.0.1', 5, 60));
    }

    public function testBlocksWhenLimitExceeded(): void
    {
        [$limiter] = $this->limiter();

        $this->assertTrue($limiter->limit('login:foo', 2, 60));
        $this->assertTrue($limiter->limit('login:foo', 2, 60));
        $this->assertFalse($limiter->limit('login:foo', 2, 60));
    }

    public function testTracksHitCount(): void
    {
        [$limiter] = $this->limiter();

        $limiter->limit('k', 10, 60);
        $limiter->limit('k', 10, 60);
        $limiter->limit('k', 10, 60);

        $this->assertSame(3, $limiter->hits('k'));
    }

    public function testHitsIsZeroWhenUnused(): void
    {
        [$limiter] = $this->limiter();
        $this->assertSame(0, $limiter->hits('never'));
    }

    public function testIsLimitedReturnsFalseUnderMax(): void
    {
        [$limiter] = $this->limiter();

        $limiter->limit('k', 3, 60);
        $this->assertFalse($limiter->isLimited('k', 3));
    }

    public function testIsLimitedReturnsTrueAtMax(): void
    {
        [$limiter] = $this->limiter();

        $limiter->limit('k', 2, 60);
        $limiter->limit('k', 2, 60);
        $this->assertTrue($limiter->isLimited('k', 2));
    }

    public function testResetClearsHits(): void
    {
        [$limiter] = $this->limiter();

        $limiter->limit('k', 5, 60);
        $limiter->limit('k', 5, 60);
        $this->assertSame(2, $limiter->hits('k'));

        $limiter->reset('k');
        $this->assertSame(0, $limiter->hits('k'));
    }

    public function testKeysAreIndependent(): void
    {
        [$limiter] = $this->limiter();

        $this->assertTrue($limiter->limit('a', 1, 60));
        $this->assertFalse($limiter->limit('a', 1, 60));
        $this->assertTrue($limiter->limit('b', 1, 60));
    }

    public function testSpecialCharactersInKeyAreSanitized(): void
    {
        [$limiter] = $this->limiter();

        $this->assertTrue($limiter->limit('user:foo@bar.com', 3, 60));
        $this->assertSame(1, $limiter->hits('user:foo@bar.com'));
    }

    public function testExpiryResetsWindow(): void
    {
        $cache   = new ArrayCache();
        $limiter = new RateLimiter($cache);

        $this->assertTrue($limiter->limit('k', 1, 1));
        $this->assertFalse($limiter->limit('k', 1, 1));

        // Manually expire the entry.
        $cache->delete('rate_limit.k');
        $this->assertTrue($limiter->limit('k', 1, 1));
    }
}