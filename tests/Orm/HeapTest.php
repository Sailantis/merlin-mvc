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

    /**
     * PK-less nodes (scheduled INSERTs whose PK the DB will generate) each
     * get their own synthetic slot: they must NOT share the identity index,
     * where every null-PK node of a class would collapse to the same key
     * and persisting two of them would silently keep only the last.
     */
    public function testPkLessNodesDoNotCollapseIntoOneSlot(): void
    {
        $heap = new Heap();

        $a  = new stdClass();
        $b  = new stdClass();
        $na = new Node(Article::class, ['id' => null], ['title' => 'A'], Node::SCHEDULED_INSERT);
        $nb = new Node(Article::class, ['id' => null], ['title' => 'B'], Node::SCHEDULED_INSERT);

        $heap->attach($a, $na);
        $heap->attach($b, $nb);

        $this->assertSame(2, $heap->count());
        $this->assertSame(['A', 'B'], array_map(fn($n) => $n->data['title'], $heap->scheduled()));

        // Both entities resolve to their OWN node.
        $this->assertSame($na, $heap->find($a));
        $this->assertSame($nb, $heap->find($b));
    }

    /**
     * Re-attaching an entity whose node was replaced by the EM's backfill
     * (null-PK -> real PK) releases the synthetic slot: no ghost entries.
     */
    public function testReattachAfterBackfillReplacesSyntheticSlot(): void
    {
        $heap   = new Heap();
        $entity = new stdClass();

        $heap->attach($entity, new Node(Article::class, ['id' => null], ['title' => 'T'], Node::SCHEDULED_INSERT));
        $this->assertSame(1, $heap->count());

        // EM backfill: MANAGED node with the real PK replaces the scheduled one.
        $managed = new Node(Article::class, ['id' => 5], ['title' => 'T'], Node::MANAGED);
        $heap->attach($entity, $managed);

        $this->assertSame(1, $heap->count()); // slot + identity never both live
        $this->assertSame($managed, $heap->find($entity));
        $this->assertSame($managed, $heap->findById(Article::class, ['id' => 5]));

        // The stale scheduled node resolves to nothing.
        $this->assertSame([], $heap->scheduled());
    }

    public function testDetachPkLessScheduledNodeCleansSlot(): void
    {
        $heap   = new Heap();
        $entity = new stdClass();

        $heap->attach($entity, new Node(Article::class, ['id' => null], [], Node::SCHEDULED_INSERT));
        $heap->detach($entity);

        $this->assertSame(0, $heap->count());
        $this->assertSame([], $heap->scheduled());
        $this->assertNull($heap->find($entity));
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

    public function testEntityForResolvesAttachedNodes(): void
    {
        $heap   = new Heap();
        $entity = new stdClass();
        $node   = new Node(Article::class, ['id' => 9], ['id' => 9], Node::MANAGED);

        $heap->attach($entity, $node);

        $this->assertSame($entity, $heap->entityFor($node));
        $this->assertNull($heap->entityFor(new Node(Article::class, ['id' => 10], [])));
    }

    public function testEntityForStaleNodeIsNull(): void
    {
        $heap   = new Heap();
        $entity = new stdClass();
        $node   = new Node(Article::class, ['id' => 3], []);

        $heap->attach($entity, $node);
        $heap->detach($entity);

        // Detached: the reverse index must not resolve the dropped node.
        $this->assertNull($heap->entityFor($node));
    }

    public function testEntityForAfterReplaceKeepsNewestEntity(): void
    {
        $heap    = new Heap();
        $first   = new stdClass();
        $second  = new stdClass();
        $nodeOne = new Node(Article::class, ['id' => 4], [], Node::MANAGED);
        $nodeTwo = new Node(Article::class, ['id' => 4], [], Node::MANAGED);

        $heap->attach($first, $nodeOne);

        // Same identity, different entity object: attach() replaces the
        // mapping — entityFor must resolve the NEWEST pairing both ways.
        $heap->attach($second, $nodeTwo);

        $this->assertSame($second, $heap->entityFor($nodeTwo));
        $this->assertSame(1, $heap->count());
    }
}