<?php

declare(strict_types=1);

namespace Azera\Tests\Orm;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Db/TestDatabase.php';

use Azera\AppContext;
use Azera\Db\DatabaseManager;
use Azera\Orm\Attribute\Column;
use Azera\Orm\Cast\Casts;
use Azera\Orm\EntityManager;
use Azera\Orm\FastHydrator;
use Azera\Orm\Metadata;
use Azera\Orm\Model;
use Azera\Orm\Storage\PdoStore;
use Azera\Orm\Storage\StoreManager;
use Azera\Tests\Db\TestDatabase;
use PHPUnit\Framework\TestCase;

/* ------------------------------------------------------------- fixtures */

class CastedArticle extends Model
{
    #[Column(type: 'int')]
    public $id;

    public $title;

    /** Portable json default (inferred from `array` too). */
    #[Column(type: 'json')]
    public $tags;

    #[Column(name: 'price_cents', type: 'int')]
    public $priceCents;

    #[Column(type: 'float')]
    public $rating;

    #[Column(type: 'bool')]
    public $published;

    /** pg native array column — cast declared, not inferred. */
    #[Column(name: 'labels', type: 'pgarray')]
    public $labels;

    /** Custom-cast target (registered per-test). */
    #[Column(type: 'upper')]
    public $slug;
}

class PlainArticle extends Model
{
    public $id;

    public $title;
}

/* ---------------------------------------------------------------- tests */

final class CastTest extends TestCase
{
    private TestDatabase $db;
    private AppContext $ctx;
    private EntityManager $em;

    protected function setUp(): void
    {
        $this->db  = new TestDatabase('pgsql');
        $this->ctx = new AppContext();
        AppContext::setInstance($this->ctx);
        FastHydrator::clear();
        Metadata::clear();
        Casts::clear(); // registry is process-static: drop custom registrations

        $dbm = new DatabaseManager();
        $dbm->set('default', $this->db);
        $dbm->set('read', $this->db);
        $dbm->set('write', $this->db);
        $this->ctx->set(DatabaseManager::class, $dbm);

        $stores = new StoreManager();
        $stores->set('sql', 'default', fn() => new PdoStore($dbm, 'read', 'write'));
        $stores->setDefault('sql', 'default');
        $this->ctx->set(StoreManager::class, $stores);

        $this->em = $this->ctx->entityManager();
    }

    protected function tearDown(): void
    {
        AppContext::reset();
    }

    private function hydratedCasted(array $row): CastedArticle
    {
        [$entity] = FastHydrator::for(CastedArticle::class)->hydrate(
            $this->em->heap(),
            $row
        );

        return $entity;
    }

    private function fullRow(array $overrides = []): array
    {
        return array_merge([
            'id'          => '7',
            'title'       => 'T',
            'tags'        => null,
            'price_cents' => null,
            'rating'      => null,
            'published'   => null,
            'labels'      => null,
            'slug'        => null,
        ], $overrides);
    }

    /** All logged queries except tx control. */
    private function dataQueries(): array
    {
        return array_values(array_filter(
            $this->db->queries,
            fn($q) => !in_array($q['sql'], ['BEGIN', 'COMMIT', 'ROLLBACK'], true)
        ));
    }

    /* ------------------------------------------------- scalar decodes */

    public function testHydrateCoercesNumericStringsInPropertyAndSnapshot(): void
    {
        // Stringifying-driver row: numerics as strings.
        $e = $this->hydratedCasted($this->fullRow([
            'price_cents' => '1200',
            'rating'      => '4.5',
            'published'   => '1',
        ]));

        $this->assertSame(7, $e->id);
        $this->assertSame(1200, $e->priceCents);
        $this->assertSame(4.5, $e->rating);
        $this->assertTrue($e->published);

        // The node snapshot must hold the COERCED values too, otherwise
        // diff() compares int(1200) vs '1200' and flags a phantom change.
        $node = $this->em->heap()->find($e);
        $this->assertSame(1200, $node->data['price_cents']);
        $this->assertSame(4.5, $node->data['rating']);
        $this->assertTrue($node->data['published']);
    }

    public function testHydratedUnchangedEntityPersistsNothing(): void
    {
        $e = $this->hydratedCasted($this->fullRow([
            'price_cents' => '1200',
            'rating'      => '4.5',
            'published'   => '1',
        ]));

        $this->em->persist($e);
        $this->em->flush();

        $data = array_values(array_filter(
            $this->db->queries,
            fn($q) => !in_array($q['sql'], ['BEGIN', 'COMMIT', 'ROLLBACK'], true)
        ));

        $this->assertSame([], $data, 'unchanged hydrated entity must not UPDATE');
    }

    public function testBoolDecodeCoversPgAndMysqlLiterals(): void
    {
        $t = $this->hydratedCasted($this->fullRow(['published' => 't']));
        $f = $this->hydratedCasted($this->fullRow(['published' => '0', 'id' => '2']));
        $this->assertTrue($t->published);
        $this->assertFalse($f->published);
    }

    public function testBoolDecodeThrowsOnUnknownLiteral(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->hydratedCasted($this->fullRow(['published' => 'maybe']));
    }

    /* ---------------------------------------------------------- json */

    public function testJsonDecodeOnHydrate(): void
    {
        $e = $this->hydratedCasted($this->fullRow(['tags' => '["a","b"]']));

        $this->assertSame(['a', 'b'], $e->tags);

        // Snapshot holds the RAW string — diff stays `!==` on scalars.
        $node = $this->em->heap()->find($e);
        $this->assertSame('["a","b"]', $node->data['tags']);
    }

    public function testJsonEncodeOnInsertAndNoPhantomUpdate(): void
    {
        $e = new CastedArticle();
        $e->id     = 1;
        $e->title  = 'T';
        $e->tags   = ['a', 'b'];
        $e->labels = ['x'];

        $this->em->persist($e);
        $this->em->flush();

        $q = $this->dataQueries()[0];
        $this->assertStringContainsString('INSERT INTO', $q['sql']);
        $this->assertSame(['a', 'b'], json_decode($q['params'][2], true));
        $this->assertSame('{x}', $q['params'][3], 'pgarray literal on insert');

        // Second flush: snapshot holds the encoded strings, diff clean.
        $this->em->persist($e);
        $this->em->flush();

        $this->assertSame(1, count($this->dataQueries()), 'no UPDATE after re-persist');
    }

    public function testJsonDecodeRoundTripThroughFind(): void
    {
        $this->db->setMockResults([
            [
                [
                    'id'          => 1,
                    'title'       => 'T',
                    'tags'        => '{"x":1}',
                    'price_cents' => null,
                    'rating'      => null,
                    'published'   => null,
                    'labels'      => null,
                    'slug'        => null,
                ]
            ]
        ]);

        $e = $this->em->find(CastedArticle::class, ['id' => 1]);

        $this->assertSame(['x' => 1], $e->tags);
    }

    public function testJsonDecodeThrowsOnCorruptStoredJson(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->hydratedCasted($this->fullRow(['tags' => '{not json']));
    }

    /* ------------------------------------------------------- pgarray */

    public function testPgArrayDecodeParsesQuotedAndNullElements(): void
    {
        $e = $this->hydratedCasted($this->fullRow([
            'labels' => '{1,"a b","c\\"d",NULL,2.5,t}',
        ]));

        $this->assertSame([1, 'a b', 'c"d', null, 2.5, true], $e->labels);
    }

    public function testPgArrayDecodeIgnoresWhitespaceAndCoercesQuotedStrings(): void
    {
        // pg's lexer skips whitespace between tokens; quoted content keeps
        // its type (string) even when it looks numeric/bool-ish.
        $e = $this->hydratedCasted($this->fullRow([
            'labels' => '{ 1, "42" , "NULL" , t , "  padded  " }',
        ]));

        $this->assertSame([1, '42', 'NULL', true, '  padded  '], $e->labels);
    }

    public function testPgArrayEncodeQuotesAndEscapes(): void
    {
        $e = new CastedArticle();
        $e->id     = 1;
        $e->labels = ['a b', 'c"d', null, '', 'NULL'];

        $this->em->persist($e);
        $this->em->flush();

        $q = $this->dataQueries()[0];
        $this->assertStringContainsString('INSERT INTO', $q['sql']);
        // 'NULL' string MUST be quoted (ambiguity), real null stays NULL el.
        // Only id + labels are set → labels is the second bound param.
        $this->assertSame(
            '{"a b","c\\"d",NULL,"","NULL"}',
            $q['params'][1]
        );
    }

    public function testPgArrayEncodesAndRoundTripsNestedArrays(): void
    {
        $e = new CastedArticle();
        $e->id     = 1;
        $e->labels = [[1, 2], [3, null]];

        $this->em->persist($e);
        $this->em->flush();

        // pg 2-D literal; dimension regularity is validated server-side
        // (ragged shapes bind, then pg rejects the INSERT).
        $q = $this->dataQueries()[0];
        $this->assertSame('{{1,2},{3,NULL}}', $q['params'][1]);

        // decode() parses the full grammar back to nested arrays.
        $round = FastHydrator::for(CastedArticle::class)
            ->hydrate($this->em->heap(), ['id' => 2, 'labels' => '{{1,2},{3,NULL}}']);
        $this->assertSame([[1, 2], [3, null]], $round[0]->labels);
    }

    public function testPgArrayThrowsBeyondSixDimensions(): void
    {
        $e = new CastedArticle();
        $e->id = 1;
        // 7-deep: pg's own dimension limit.
        $e->labels = [[[[[[[1]]]]]]];

        $this->expectException(\RuntimeException::class);
        $this->em->persist($e);
        $this->em->flush();
    }

    public function testPgArrayEmptyArrayEncodesEmptyLiteral(): void
    {
        $e = new CastedArticle();
        $e->id     = 1;
        $e->labels = [];

        $this->em->persist($e);
        $this->em->flush();

        $q = $this->dataQueries()[0];
        $this->assertSame('{}', $q['params'][1]);
    }

    /* ------------------------------------------------- custom casts */

    public function testCustomCastRegistrationAndDecode(): void
    {
        Casts::register('upper', new class implements \Azera\Orm\Cast\Cast
        {
            public function encode(mixed $value): mixed
            {
                return $value;
            }
            public function decode(mixed $value): mixed
            {
                return $value === null ? null : strtoupper((string) $value);
            }
        });

        FastHydrator::clear(); // recompile plan including the new cast

        $e = $this->hydratedCasted($this->fullRow(['slug' => 'hello']));

        $this->assertSame('HELLO', $e->slug);
    }

    /* ------------------------------------------ cast-free fast path */

    public function testPlainClassKeepsRawValues(): void
    {
        $this->db->setMockResults([[['id' => '5', 'title' => 'T']]]);

        $e = $this->em->find(PlainArticle::class, ['id' => 5]);

        // No casts compiled for PlainArticle — raw passthrough.
        $this->assertSame('5', $e->id);
    }

    public function testCastsRegistryBuiltins(): void
    {
        $this->assertSame(
            ['int', 'float', 'bool', 'json', 'pgarray'],
            Casts::types()
        );

        $this->assertNull(Casts::for('datetime'), 'datetime decode stays opt-in later');
    }
}