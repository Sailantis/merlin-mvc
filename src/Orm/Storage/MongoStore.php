<?php

declare(strict_types=1);

namespace Azera\Orm\Storage;

use Azera\Db\ModelMapping;
use Azera\Orm\Metadata;
use MongoDB\Client;
use MongoDB\Collection as MongoCollection;

/**
 * MongoDB backend of the {@see Store} seam over the mongodb/mongodb library.
 *
 * The stack is two layers, NOT two alternatives (unlike redis's phpredis vs
 * predis): ext-mongodb (PECL) is THE driver — MongoDB\Driver\Manager, BSON
 * encoding, wire protocol — and mongodb/mongodb (composer) is the pure-PHP
 * convenience API on top of it ({@see Client}, {@see MongoCollection}). The
 * library cannot run without the extension; using this store = using both.
 *
 * OWNS its connection (the inverse of PdoStore's borrow model): mongo has no
 * role-based read/write split in the DatabaseManager, so the store wraps one
 * {@see Client} and resolves the per-class collection from metadata
 * (`collection` ?? snake/plural convention). A class belongs to exactly one
 * collection, declared by #[Document(collection)].
 *
 * Constructor accepts EITHER a Client (production) OR a collection resolver
 * `fn(string $name): MongoCollection` — the test seam: an in-memory fake
 * collection keeps the suite hermetic (no live server, no flaky CI).
 *
 * Rows are plain assoc arrays keyed by the metadata COLUMN names — identical
 * shape contract to PdoStore — so EntityManager's write pipeline, heap diff,
 * and FastHydrator work unchanged.
 *
 * Identity: mongo's `_id` is THE PK — single, always present (driver-generated
 * ObjectId on insert when omitted). The metadata pk convention (*_id marks)
 * already resolves `$_id` for documents, and insert backfill maps the
 * inserted id onto it.
 *
 * Transactions: no-ops. Multi-document ACID needs replica-set sessions —
 * deliberately deferred (documented); the Store seam's begin/commit/rollback
 * is satisfied structurally so the EM pipeline works against single-server
 * deployments.
 */
final class MongoStore implements Store
{
    /** @var array<string, MongoCollection> class => resolved collection */
    private array $collections = [];

    /** @var ?Client wrapped client (null when a resolver was injected) */
    private ?Client $client;

    /** @var ?callable(string): MongoCollection collection resolver (null when a client was injected) */
    private $resolver;

    /**
     * @param Client|callable(string): MongoCollection $clientOrResolver
     */
    public function __construct(
        Client|callable $clientOrResolver,
        private string $database = 'azera',
    ) {
        if ($clientOrResolver instanceof Client) {
            $this->client   = $clientOrResolver;
            $this->resolver = null;
        } else {
            $this->client   = null;
            $this->resolver = $clientOrResolver;
        }
    }

    public function insertOne(string $class, array $data): array
    {
        $meta = Metadata::for($class);
        $pk   = self::pkName($meta);

        $doc = $data;
        // Unset _id (null) = let the driver generate the ObjectId; anything
        // set passes through as-is (ObjectId or string, driver maps it).
        if ($pk === '_id' && ($doc[$pk] ?? null) === null) {
            unset($doc[$pk]);
        }

        $result = $this->collection($meta)->insertOne($doc);
        $id     = $result->getInsertedId();

        return [
            'row' => null,
            'id'  => is_object($id) ? (string) $id : $id,
        ];
    }

    public function updateOne(string $class, array $data, array $id): array
    {
        $meta = Metadata::for($class);

        // $set: partial update — exactly the EM's changed-columns diff.
        $this->collection($meta)->updateOne(
            self::prepareFilter($id),
            ['$set' => $data],
        );

        return ['row' => null, 'id' => null];
    }

    public function deleteOne(string $class, array $id): void
    {
        $meta = Metadata::for($class);
        $this->collection($meta)->deleteOne(self::prepareFilter($id));
    }

    public function findBy(string $class, array $where): array
    {
        $meta = Metadata::for($class);
        // CursorInterface is iterable — no ->toArray() coupling; works for
        // the real driver cursor and the test fake alike.
        return iterator_to_array($this->collection($meta)->find(self::prepareFilter($where)));
    }

    public function findByPk(string $class, array $id): ?array
    {
        $meta = Metadata::for($class);
        $doc  = $this->collection($meta)->findOne(self::prepareFilter($id));

        return $doc === null ? null : (array) $doc;
    }

    public function count(string $class, array $where = []): int
    {
        $meta = Metadata::for($class);
        return $this->collection($meta)->countDocuments(self::prepareFilter($where));
    }

    /* --------------------------------------------------- transactions */

    /**
     * No-ops: multi-document ACID needs replica-set sessions (deferred).
     * Kept structural so the EM pipeline never branches on store type.
     */
    public function begin(): void {}

    public function commit(): void {}

    public function rollback(): void {}

    public function inTransaction(): bool
    {
        return false;
    }

    /* -------------------------------------------------------- helpers */

    /**
     * Per-class collection, resolved once per class per store instance.
     * Name: metadata `collection` (#[Document(collection)]) — falling back
     * to the SQL-style snake/plural convention when the attribute omits it.
     *
     * Deliberately NO return type: MongoDB\Collection is final (no
     * interface), so the resolver-seam fake (tests) duck-types on the same
     * method surface — production passes the real object.
     */
    private function collection(array $meta)
    {
        $class = $meta['class'];

        if (isset($this->collections[$class])) {
            return $this->collections[$class];
        }

        $name = $meta['collection'] ?? null;
        if ($name === null || $name === '') {
            $short = (new \ReflectionClass($class))->getShortName();
            $name  = ModelMapping::convertModelToSource($short);
        }

        $collection = $this->resolver !== null
            ? ($this->resolver)($name)
            : $this->client->selectCollection($this->database, $name);

        return $this->collections[$class] = $collection;
    }

    /**
     * The document's PK column name. Metadata pk convention resolves `$_id`
     * for documents (the *_id marks); fallback '_id' mirrors Document::pkField().
     */
    private static function pkName(array $meta): string
    {
        foreach ($meta['columns'] as $col) {
            if ($col['pk']) {
                return $col['name'];
            }
        }

        return '_id';
    }

    /**
     * Filter preparation: THE _id TYPE CONTRACT. The server stores an
     * ObjectId (the driver generates one when insert omits _id), but the
     * EM round-trips the STRING backfill (insertOne returns (string) id →
     * entity → node->id → next filter). Mongo matches by TYPE: a string
     * filter against an ObjectId doc matches NOTHING — updates/deletes
     * silently no-op and the follow-up save even re-INSERTs (duplicate).
     * Fix at the store boundary: any 24-hex-char string _id IS an ObjectId
     * backfill → cast back for the wire. Non-matching strings (caller-set
     * custom ids) and ObjectIds pass through untouched.
     */
    private static function prepareFilter(array $filter): array
    {
        if (!isset($filter['_id']) || !\is_string($filter['_id'])) {
            return $filter;
        }

        $id = $filter['_id'];
        if (preg_match('/^[0-9a-fA-F]{24}$/', $id) === 1) {
            $filter['_id'] = new \MongoDB\BSON\ObjectId($id);
        }

        return $filter;
    }
}