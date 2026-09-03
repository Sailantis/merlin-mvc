<?php

namespace Azera\Tests\Orm;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/Fixtures/Article.php';

use Azera\Orm\Heap;
use Azera\Orm\Node;
use Azera\Tests\Orm\Fixtures\Article;
use PHPUnit\Framework\TestCase;
use stdClass;

class HeapTest extends TestCase
{
    public function testAttachAndFindById(): void
    {
        $heap   = new Heap();
        $entity = new stdClass();
        $node   = new Node(Article::class, ['id' => 7], ['id' => 7, 'title' => 'T']);

        $heap->attach($entity, $node);

        $this->assertSame($node, $heap->findById(Article::class, ['id' => 7]));
        $this->assertSame($node, $heap->find($entity));
        $this->assertSame(1, $heap->count());
    }

    public function testCompositeKeyAssocOrderInsensitive(): void
    {
        $heap   = new Heap();
        $node   = new Node(Article::class, ['tenant_id' => 'a', 'id' => 7], []);
        $entity = new stdClass();

        $heap->attach($entity, $node);

        $this->assertSame($node, $heap->findById(Article::class, ['tenant_id' => 'a', 'id' => 7]));
        $this->assertSame($node, $heap->findById(Article::class, ['id' => 7, 'tenant_id' => 'a']));
    }

    public function testCompositeKeyListOrderSensitive(): void
    {
        $heap   = new Heap();
        $node   = new Node(Article::class, [0 => 'a', 1 => 7], []);
        $entity = new stdClass();

        $heap->attach($entity, $node);
        $this->assertSame($node, $heap->findById(Article::class, [0 => 'a', 1 => 7]));
    }

    public function testAttachSameObjectUnderNewIdentityDropsOldMapping(): void
    {
        $heap   = new Heap();
        $entity = new stdClass();

        $heap->attach($entity, new Node(Article::class, ['id' => 7], []));
        $heap->attach($entity, new Node(Article::class, ['id' => 8], []));

        $this->assertNull($heap->findById(Article::class, ['id' => 7]));
        $this->assertNotNull($heap->findById(Article::class, ['id' => 8]));
    }

    public function testScheduledReturnsOnlyScheduledNodes(): void
    {
        $heap = new Heap();

        $heap->attach(new stdClass(), new Node(Article::class, [], [], Node::NEW));
        $heap->attach(new stdClass(), new Node(Article::class, ['id' => 1], [], Node::MANAGED));
        $ins = new Node(Article::class, ['id' => 2], [], Node::SCHEDULED_INSERT);
        $upd = new Node(Article::class, ['id' => 3], [], Node::SCHEDULED_UPDATE);
        $heap->attach(new stdClass(), $ins);
        $heap->attach(new stdClass(), $upd);
        $heap->attach(new stdClass(), new Node(Article::class, ['id' => 4], [], Node::SCHEDULED_DELETE));

        $sched = $heap->scheduled();
        $this->assertCount(3, $sched);
        $this->assertSame($ins, $sched[0]);
        $this->assertSame($upd, $sched[1]);
    }

    public function testDetachRemovesBothIndexes(): void
    {
        $heap   = new Heap();
        $node   = new Node(Article::class, ['id' => 7], []);
        $entity = new stdClass();

        $heap->attach($entity, $node);
        $heap->detach($entity);

        $this->assertNull($heap->findById(Article::class, ['id' => 7]));
        $this->assertNull($heap->find($entity));
        $this->assertSame(0, $heap->count());
    }

    public function testResetStateWipesEverything(): void
    {
        $heap = new Heap();
        $heap->attach(new stdClass(), new Node(Article::class, ['id' => 1], []));
        $heap->attach(new stdClass(), new Node(Author::class, ['id' => 2], []));

        $heap->resetState();

        $this->assertSame(0, $heap->count());
        $this->assertSame([], $heap->all());
        $this->assertSame([], $heap->scheduled());
    }
}