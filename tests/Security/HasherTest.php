<?php

namespace Azera\Tests\Security;

require_once __DIR__ . '/../../vendor/autoload.php';

use Azera\Security\Hasher;
use PHPUnit\Framework\TestCase;

class HasherTest extends TestCase
{
    public function testMakeReturnsHashString(): void
    {
        $hasher = new Hasher();
        $hash   = $hasher->make('secret');

        $this->assertIsString($hash);
        $this->assertNotSame('secret', $hash);
        $this->assertTrue(password_get_info($hash)['algo'] !== null);
    }

    public function testVerifyAcceptsCorrectPassword(): void
    {
        $hasher = new Hasher();
        $hash   = $hasher->make('p@ssw0rd');

        $this->assertTrue($hasher->verify('p@ssw0rd', $hash));
    }

    public function testVerifyRejectsWrongPassword(): void
    {
        $hasher = new Hasher();
        $hash   = $hasher->make('p@ssw0rd');

        $this->assertFalse($hasher->verify('wrong', $hash));
    }

    public function testVerifyRejectsEmptyHash(): void
    {
        $hasher = new Hasher();
        $this->assertFalse($hasher->verify('anything', ''));
    }

    public function testNeedsRehashReturnsTrueForOutdatedCost(): void
    {
        // A bcrypt hash made with cost 4 should need rehash when the
        // hasher is configured for cost 10.
        $oldHash = password_hash('secret', PASSWORD_BCRYPT, ['cost' => 4]);
        $hasher  = new Hasher(PASSWORD_BCRYPT, ['cost' => 10]);

        $this->assertTrue($hasher->needsRehash($oldHash));
    }

    public function testNeedsRehashReturnsFalseForCurrentCost(): void
    {
        $hasher = new Hasher(PASSWORD_BCRYPT, ['cost' => 10]);
        $hash   = $hasher->make('secret');

        $this->assertFalse($hasher->needsRehash($hash));
    }

    public function testTokenReturnsHexOfExpectedLength(): void
    {
        $hasher = new Hasher();
        $token  = $hasher->token(16);

        $this->assertSame(32, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    public function testTokenIsUnique(): void
    {
        $hasher = new Hasher();
        $this->assertNotSame($hasher->token(), $hasher->token());
    }

    public function testCustomAlgorithmAndOptions(): void
    {
        $hasher = new Hasher(PASSWORD_BCRYPT, ['cost' => 4]);
        $hash   = $hasher->make('secret');

        $this->assertTrue($hasher->verify('secret', $hash));
        $info = password_get_info($hash);
        $this->assertSame(4, $info['options']['cost'] ?? null);
    }
}