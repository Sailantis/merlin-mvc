<?php

namespace Azera\Tests\Security;

require_once __DIR__ . '/../../vendor/autoload.php';

use Azera\Security\AuthManagerInterface;
use Azera\Security\GuardInterface;
use PHPUnit\Framework\TestCase;

class AuthContractsTest extends TestCase
{
    public function testGuardInterfaceContract(): void
    {
        $guard = new class implements GuardInterface
        {
            public bool $loggedOut = false;

            public function attempt(array $credentials): bool
            {
                return $credentials['password'] ?? '' === 'secret';
            }
            public function check(): bool
            {
                return true;
            }
            public function user(): mixed
            {
                return ['id' => 1, 'name' => 'admin'];
            }
            public function id(): mixed
            {
                return 1;
            }
            public function logout(): void
            {
                $this->loggedOut = true;
            }
        };

        $this->assertTrue($guard->check());
        $this->assertSame(1, $guard->id());
        $this->assertSame(['id' => 1, 'name' => 'admin'], $guard->user());
        $guard->logout();
        $this->assertTrue($guard->loggedOut);
    }

    public function testAuthManagerInterfaceContract(): void
    {
        $guard = new class implements GuardInterface
        {
            public function attempt(array $c): bool
            {
                return true;
            }
            public function check(): bool
            {
                return true;
            }
            public function user(): mixed
            {
                return ['id' => 7];
            }
            public function id(): mixed
            {
                return 7;
            }
            public function logout(): void {}
        };

        $manager = new class($guard) implements AuthManagerInterface
        {
            private array $guards = [];
            private ?GuardInterface $current = null;

            public function __construct(GuardInterface $guard)
            {
                $this->guards['web'] = $guard;
                $this->current       = $guard;
            }
            public function addGuard(string $name, GuardInterface $guard): void
            {
                $this->guards[$name] = $guard;
            }
            public function guard(?string $name = null): GuardInterface
            {
                $name ??= 'web';
                if (!isset($this->guards[$name])) {
                    throw new \InvalidArgumentException("Unknown guard: $name");
                }
                return $this->current = $this->guards[$name];
            }
            public function currentGuard(): GuardInterface
            {
                return $this->current;
            }
            public function attempt(array $credentials): bool
            {
                return $this->current->attempt($credentials);
            }
            public function logout(): void
            {
                $this->current->logout();
            }
            public function check(): bool
            {
                return $this->current->check();
            }
            public function id(): mixed
            {
                return $this->current->id();
            }
        };

        $this->assertSame($guard, $manager->currentGuard());
        $this->assertTrue($manager->check());
        $this->assertSame(7, $manager->id());

        $manager->addGuard('api', $guard);
        $this->assertSame($guard, $manager->guard('api'));
    }
}