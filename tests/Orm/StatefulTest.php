<?php

namespace Azera\Tests\Orm;

require_once __DIR__ . '/../../vendor/autoload.php';

use Azera\Orm\Stateful;
use PHPUnit\Framework\TestCase;

/**
 * Minimal concrete Stateful — the base is abstract, tests need an instance.
 */
class TrackedObject extends Stateful
{
    public $name;
    public $email;
    public $age = 0;

    /** @internal excluded from state tracking */
    public $__cache = [];

    public function changedValues(): array
    {
        return $this->__getChangedValues();
    }

    public function updateState(array $values): void
    {
        $this->__updateState($values);
    }
}

/**
 * A subclass adding its own internal property — must also be excluded.
 */
class TrackedChild extends TrackedObject
{
    public $__metadata;

    public function extraState(): array
    {
        return $this->__getChangedValues();
    }
}

class StatefulTest extends TestCase
{
    public function testSnapshotRoundTrip(): void
    {
        $o = new TrackedObject();
        $o->name = 'Alice';
        $o->saveState();

        $this->assertFalse($o->hasChanged());

        $o->name = 'Bob';
        $this->assertTrue($o->hasChanged());

        $o->loadState();
        $this->assertSame('Alice', $o->name);
        $this->assertFalse($o->hasChanged());
    }

    public function testChangedValuesReturnsOnlyDiff(): void
    {
        $o = new TrackedObject();
        $o->name  = 'Alice';
        $o->email = 'a@x.dev';
        $o->saveState();

        $o->name = 'Bob';
        $o->age  = 42;

        $this->assertSame(['name' => 'Bob', 'age' => 42], $o->changedValues());
    }

    public function testNoSnapshotMeansAllFieldsChanged(): void
    {
        $o = new TrackedObject();
        $o->name = 'Alice';

        // get_object_vars includes declared-but-unset props as null
        $this->assertSame(
            ['name' => 'Alice', 'email' => null, 'age' => 0],
            $o->changedValues()
        );
    }

    public function testInternalPropertiesExcluded(): void
    {
        $o = new TrackedObject();
        $o->name    = 'Alice';
        $o->__cache = ['x' => 1];
        $o->saveState();

        $o->__cache = ['y' => 2];
        $o->name    = 'Bob';

        $this->assertSame(['name' => 'Bob'], $o->changedValues());
    }

    public function testSubclassInternalPropertiesExcludedToo(): void
    {
        $o = new TrackedChild();
        $o->name       = 'Alice';
        $o->__metadata = ['k' => 'v'];
        $o->saveState();

        $o->__metadata = ['z' => 'w'];
        $o->name       = 'Bob';

        $this->assertSame(['name' => 'Bob'], $o->extraState());
    }

    public function testGetStateReturnsSnapshot(): void
    {
        $o = new TrackedObject();
        $this->assertNull($o->getState());

        $o->name = 'Alice';
        $o->saveState();

        $this->assertSame('Alice', $o->getState()?->name);
        $this->assertSame('Alice', $o->name);

        $o->name = 'Bob';
        $this->assertSame('Alice', $o->getState()?->name);
    }

    public function testLoadStateWithoutSnapshotIsNoop(): void
    {
        $o = new TrackedObject();
        $o->name = 'Alice';

        $this->assertSame($o, $o->loadState());
        $this->assertSame('Alice', $o->name);
    }

    public function testUpdateStateKeepsSnapshotInSync(): void
    {
        $o = new TrackedObject();
        $o->name = 'Alice';
        $o->saveState();

        $o->name = 'Bob';
        $o->updateState(['name' => 'Bob']);

        $this->assertFalse($o->hasChanged());
    }

    public function testObjectPropertiesSnapshotByReference(): void
    {
        $o = new TrackedObject();
        $o->email = 'a@x.dev';
        $o->saveState();

        $o->email = 'b@x.dev';
        $this->assertTrue($o->hasChanged());
        $this->assertSame(['email' => 'b@x.dev'], $o->changedValues());
    }

    /**
     * DOCUMENTS a pre-existing limitation: __getChangedValues() uses
     * array_diff_assoc, which string-casts values — two DIFFERENT arrays
     * both cast to "Array" and compare equal. Array mutations are therefore
     * invisible to the diff (only null→array transitions register). The
     * future UnitOfWork must not rely on this for array/JSON columns.
     */
    public function testArrayValueChangesAreInvisibleToDiff(): void
    {
        $o = new TrackedObject();
        $o->email = ['a@x.dev'];
        $o->saveState();

        $o->email = ['totally', 'different'];
        $this->assertFalse($o->hasChanged());
    }

    public function testNullToArrayTransitionRegisters(): void
    {
        $o = new TrackedObject();
        $o->email = null;
        $o->saveState();

        $o->email = ['a@x.dev'];
        $this->assertTrue($o->hasChanged());
    }
}