<?php

namespace Azera\Tests\Orm;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/Fixtures/Article.php';
require_once __DIR__ . '/Fixtures/Relations.php';
require_once __DIR__ . '/Fixtures/ArticleDocument.php';
require_once __DIR__ . '/Fixtures/InventoryItem.php';

use Azera\Cache\ArrayCache;
use Azera\Orm\Metadata;
use Azera\Tests\Orm\Fixtures\Article;
use Azera\Tests\Orm\Fixtures\ArticleDocument;
use Azera\Tests\Orm\Fixtures\ArticleWithRelations;
use Azera\Tests\Orm\Fixtures\Comment;
use Azera\Tests\Orm\Fixtures\InventoryItem;
use PHPUnit\Framework\TestCase;

class MetadataTest extends TestCase
{
    protected function setUp(): void
    {
        Metadata::clear();
    }

    protected function tearDown(): void
    {
        // Static L2 config must not leak into other test classes.
        Metadata::useCache(null);
        Metadata::cacheSalt(null);
        Metadata::clear();
    }

    public function testCompileInferredAndExplicitColumns(): void
    {
        $meta = Metadata::for(Article::class);

        $this->assertSame('article', $meta['source']);
        $this->assertSame('sql', $meta['store']);

        // explicit #[Column(type: int)] on $id, first *_id-named property → pk
        $this->assertTrue($meta['columns']['id']['pk']);
        $this->assertSame('int', $meta['columns']['id']['type']);

        $this->assertSame('string', $meta['columns']['title']['type']);
        $this->assertFalse($meta['columns']['title']['pk']);

        $this->assertSame('datetime', $meta['columns']['created_at']['type']);

        // transient property excluded entirely
        $this->assertArrayNotHasKey('computed', $meta['columns']);
    }

    public function testColumnNameOverride(): void
    {
        $meta = Metadata::for(Article::class);

        $this->assertSame('status_code', $meta['columns']['status']['name']);
        $this->assertSame('int', $meta['columns']['status']['type']);
        $this->assertFalse($meta['columns']['status']['pk']);
    }

    public function testRelationsCompiled(): void
    {
        $rel = Metadata::for(ArticleWithRelations::class)['relations'];

        $this->assertSame('hasOne', $rel['meta']['type']);
        // foreignKey default = source-based: article_with_relations_id
        $this->assertSame('article_with_relations_id', $rel['meta']['foreignKey']);
        $this->assertSame('join', $rel['meta']['strategy']);

        $this->assertSame('hasMany', $rel['comments']['type']);
        $this->assertSame('article_with_relations_id', $rel['comments']['foreignKey']);
        $this->assertSame('second_query', $rel['comments']['strategy']);
    }

    public function testBelongsToDefaultsForeignKeyFromRelationName(): void
    {
        $rel = Metadata::for(Comment::class)['relations'];

        $this->assertSame('belongsTo', $rel['article']['type']);
        $this->assertSame(Article::class, $rel['article']['target']);
        // foreignKey default = relation name + '_id'
        $this->assertSame('article_id', $rel['article']['foreignKey']);
        $this->assertSame('join', $rel['article']['strategy']);

        $this->assertSame('author_id', $rel['author']['foreignKey']);
        $this->assertSame('join', $rel['author']['strategy']);
    }

    public function testDocumentStoreSwitchesStoreAndCollection(): void
    {
        $meta = Metadata::for(ArticleDocument::class);

        $this->assertSame('mongo', $meta['store']);
        $this->assertSame('articles', $meta['collection']);
        $this->assertNull($meta['schema']);
        $this->assertNull($meta['readRole']);
        $this->assertNull($meta['writeRole']);
    }

    public function testTableAttributeSetsSourceAndSchema(): void
    {
        $meta = Metadata::for(InventoryItem::class);

        $this->assertSame('inventory_items', $meta['source']);
        $this->assertSame('warehouse', $meta['schema']);
    }

    public function testConnectionAttributeSetsSplitRoles(): void
    {
        $meta = Metadata::for(InventoryItem::class);

        $this->assertSame('replica', $meta['readRole']);
        $this->assertSame('primary', $meta['writeRole']);
    }

    public function testExplicitPkMarksDefineCompositeKeyAndExcludeConvention(): void
    {
        $meta = Metadata::for(InventoryItem::class);

        $this->assertTrue($meta['columns']['tenant_id']['pk']);
        $this->assertTrue($meta['columns']['item_id']['pk']);
        // Renamed *_id column with explicit pk: false — the name convention
        // must NOT leak it into the key.
        $this->assertFalse($meta['columns']['externalRef']['pk']);
        $this->assertFalse($meta['columns']['name']['pk']);

        // idFields() resolves from the marks, in declaration order.
        $this->assertSame(
            ['tenant_id', 'item_id'],
            (new \ReflectionClass(InventoryItem::class))->newInstanceWithoutConstructor()->idFields()
        );

        // pkFields mirrors idFields() exactly (declaration order).
        $this->assertSame(['tenant_id', 'item_id'], $meta['pkFields']);
    }

    public function testPkFieldsDefaultToIdWhenNoMarks(): void
    {
        $meta = Metadata::for(Article::class);

        $this->assertSame(['id'], $meta['pkFields']);
    }

    public function testPkFieldsForDocumentKeepsConventionAndMarks(): void
    {
        $meta = Metadata::for(ArticleDocument::class);

        $this->assertSame('mongo', $meta['store']);
        // Plain/mongo classes keep the id/*_id name convention: $_id is
        // pk-marked by the *_id suffix (documents commonly rely on it).
        $this->assertSame(['_id'], $meta['pkFields']);
    }

    public function testTableAndConnectionOnDocumentThrow(): void
    {
        $this->expectException(\LogicException::class);
        Metadata::for(DocumentWithTableAttr::class);
    }

    public function testDeclaredSourceOverrideWinsOverConvention(): void
    {
        // ModelTest's dummy model declares source() overrides in the Mvc
        // namespace; here the compile-time hook is proven via the schema()
        // default: non-overriding models get null.
        $this->assertNull(Metadata::for(Article::class)['schema']);
        $this->assertSame('article', Metadata::for(Article::class)['source']);
    }

    public function testL1CacheReturnsIdenticalArray(): void
    {
        $a = Metadata::for(Article::class);
        $b = Metadata::for(Article::class);

        $this->assertSame($a, $b, 'L1 cache returns the identical array');
    }

    public function testClearForcesRecompile(): void
    {
        $a = Metadata::for(Article::class);
        Metadata::clear();
        $b = Metadata::for(Article::class);

        // clear() empties L1, so the only way for() can return data again
        // is a fresh compile(). (Note: assertNotSame is meaningless for
        // arrays — PHP array === is content comparison, not identity.)
        $this->assertEquals($a, $b, 'recompiled metadata equals the original');
    }

    /* -------------------------------------------- L2 (opt-in PSR-16 backend) */

    /** Mirrors Metadata::cacheKey(): 'azera_orm_meta_' . md5(v4\0salt\0class). */
    private static function metaKey(string $class, string $salt = ''): string
    {
        return 'azera_orm_meta_' . md5("v4\0{$salt}\0{$class}");
    }

    /** All azera_orm_meta_* keys currently present in an ArrayCache backend. */
    private function metaKeys(ArrayCache $backend): array
    {
        $data = (new \ReflectionClass($backend))->getProperty('data')->getValue($backend);

        return array_values(array_filter(
            array_keys($data),
            fn(string $k) => str_starts_with($k, 'azera_orm_meta_')
        ));
    }

    public function testL2RoundTripWritesAndClearsOnlyOwnKeys(): void
    {
        $backend = new ArrayCache();
        $backend->set('other_app_key', 'keep-me');
        Metadata::useCache($backend);

        $meta = Metadata::for(Article::class);

        // One class key + the key index.
        $this->assertCount(2, $this->metaKeys($backend), 'meta key + index key written');

        $payload = $backend->get(self::metaKey(Article::class));
        $this->assertSame(Article::class, $payload['class'] ?? null, 'payload carries + verifies the class');

        Metadata::clear();

        $this->assertSame('keep-me', $backend->get('other_app_key'), 'foreign keys survive clear()');
        $this->assertSame([], $this->metaKeys($backend), 'only our keys are deleted');
    }

    public function testL2HitServesStoredPayloadAfterL1Reset(): void
    {
        $backend = new ArrayCache();
        Metadata::useCache($backend);
        Metadata::for(Article::class); // compile + write to L2

        $key = self::metaKey(Article::class);
        $poison = $backend->get($key);
        $poison['source'] = 'poisoned_from_l2';
        $backend->set($key, $poison);

        // Simulate a new worker process: L1 empty, L2 warm.
        (new \ReflectionProperty(Metadata::class, 'l1'))->setValue(null, []);

        $this->assertSame('poisoned_from_l2', Metadata::for(Article::class)['source']);
    }

    public function testForeignPayloadInL2IsTreatedAsMiss(): void
    {
        $backend = new ArrayCache();
        Metadata::useCache($backend);

        // Foreign/corrupt payload under our key (class mismatch).
        $backend->set(self::metaKey(Article::class), ['class' => 'Some\\Other\\Class']);

        $meta = Metadata::for(Article::class);

        $this->assertSame('article', $meta['source'], 'wrong-class payload is a miss → recompiled');
        $this->assertSame(Article::class, $backend->get(self::metaKey(Article::class))['class']);
    }

    public function testCacheSaltChangesKeyAndAcceptsUnsafeCharacters(): void
    {
        $backend = new ArrayCache();
        Metadata::useCache($backend);
        Metadata::for(Article::class);
        $keyNoSalt = self::metaKey(Article::class);
        $this->assertContains($keyNoSalt, $this->metaKeys($backend));

        // A new deploy hash changes the key → old entries are never
        // requested again (they expire via TTL or backend eviction).
        Metadata::cacheSalt('deploy-42:build/abc?x=y'); // deliberately key-unsafe chars
        (new \ReflectionProperty(Metadata::class, 'l1'))->setValue(null, []);
        Metadata::for(InventoryItem::class);

        $keySalted = self::metaKey(InventoryItem::class, 'deploy-42:build/abc?x=y');
        $keys = $this->metaKeys($backend);
        $this->assertContains($keyNoSalt, $keys, 'salting does not delete earlier entries');
        $this->assertContains($keySalted, $keys);
        $this->assertSame(InventoryItem::class, $backend->get($keySalted)['class']);
    }

    public function testTtlIsForwardedToBackend(): void
    {
        $key = self::metaKey(Article::class);

        $backend = new ArrayCache();
        Metadata::useCache($backend, 3600);
        Metadata::for(Article::class);

        $data = (new \ReflectionClass($backend))->getProperty('data')->getValue($backend);
        $this->assertNotNull($data[$key]['expires'], 'ttl forwarded → entry has an expiry');

        // New backend + fresh L1 → forces a fresh compile/write to L2.
        $backend = new ArrayCache();
        Metadata::useCache($backend); // no ttl
        (new \ReflectionProperty(Metadata::class, 'l1'))->setValue(null, []);
        Metadata::for(Article::class);

        $data = (new \ReflectionClass($backend))->getProperty('data')->getValue($backend);
        $this->assertNull($data[$key]['expires'], 'no ttl → backend default (no expiry)');
    }
}

/** Fixture: SQL-only attributes on a mongo document → compile throws. */
#[\Azera\Orm\Attribute\Document(collection: 'conflict')]
#[\Azera\Orm\Attribute\Table(name: 'no_sql_here')]
class DocumentWithTableAttr extends \Azera\Orm\Model
{
    public $id;
}