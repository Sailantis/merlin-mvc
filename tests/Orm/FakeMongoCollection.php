<?php

declare(strict_types=1);

namespace Azera\Tests\Orm;

use Azera\Orm\Storage\MongoStore;
use MongoDB\Collection as MongoCollection;

/**
 * In-memory MongoDB stand-in for the hermetic Store tests — the mongo
 * sibling of {@see \Azera\Tests\Db\TestDatabase} (no live server, no
 * flaky CI).
 *
 * NOT a real Collection (the library's class has no interface): the fake is
 * duck-typed to the exact surface MongoStore calls — insertOne / updateOne /
 * deleteOne / findOne / find / countDocuments — and feeds the store through
 * the resolver seam `fn(name): MongoCollection`. Result objects mirror the
 * library contracts the store consumes (getInsertedId, getModifiedCount,
 * getDeletedCount as plain ints).
 *
 * Identity: docs live as plain assoc arrays; `_id` is driver-generated
 * (auto-increment string) when omitted — mirroring the ObjectId semantic
 * the store relies on for backfill.
 */
final class FakeMongoCollection
{
    /** @var list<array<string, mixed>> */
    public array $docs = [];

    /** @var int next generated _id */
    public int $nextId = 1;

    /** @var list<array{op: string, args: array}> call log (op => insert/update/delete/find/count) */
    public array $calls = [];

    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function name(): string
    {
        return $this->name;
    }

    /* ------------------------------------------- library-like surface */

    public function insertOne(array|object $document): object
    {
        $doc = (array) $document;
        $this->calls[] = ['op' => 'insert', 'args' => [$doc]];

        $id = $doc['_id'] ?? null;
        if ($id === null) {
            $id = (string) $this->nextId++;
            $doc['_id'] = $id;
        }

        $this->docs[] = $doc;
        return new FakeInsertOneResult($id);
    }

    public function updateOne(array|object $filter, array|object $update): object
    {
        $filter = (array) $filter;
        $update = (array) $update;
        $this->calls[] = ['op' => 'update', 'args' => [$filter, $update]];

        $index = $this->matchIndex($filter);
        if ($index === null) {
            return new FakeUpdateResult(0);
        }

        $set = $update['$set'] ?? [];
        foreach ($set as $k => $v) {
            $this->docs[$index][$k] = $v;
        }

        return new FakeUpdateResult(1);
    }

    public function deleteOne(array|object $filter): object
    {
        $filter = (array) $filter;
        $this->calls[] = ['op' => 'delete', 'args' => [$filter]];

        $index = $this->matchIndex($filter);
        if ($index === null) {
            return new FakeDeleteResult(0);
        }

        array_splice($this->docs, $index, 1);
        return new FakeDeleteResult(1);
    }

    public function findOne(array|object $filter = []): ?array
    {
        $filter = (array) $filter;
        $this->calls[] = ['op' => 'find', 'args' => [$filter]];

        $index = $this->matchIndex($filter);
        return $index === null ? null : $this->docs[$index];
    }

    public function find(array|object $filter = []): object
    {
        $filter = (array) $filter;
        $this->calls[] = ['op' => 'find', 'args' => [$filter]];

        $out = [];
        foreach ($this->docs as $doc) {
            if ($this->matches($doc, $filter)) {
                $out[] = $doc;
            }
        }

        return new FakeCursor($out);
    }

    public function countDocuments(array|object $filter = []): int
    {
        $filter = (array) $filter;
        $this->calls[] = ['op' => 'count', 'args' => [$filter]];

        $n = 0;
        foreach ($this->docs as $doc) {
            if ($this->matches($doc, $filter)) {
                $n++;
            }
        }

        return $n;
    }

    /* --------------------------------------------- matching internals */

    /**
     * Equality matching only — the surface the Store seam's WHERE
     * (PK field => value, field => value) needs.
     */
    private function matches(array $doc, array $filter): bool
    {
        foreach ($filter as $k => $v) {
            // Mongo semantics: a null filter value also matches a MISSING
            // field (the EM writes explicit nulls for unset columns).
            if (($doc[$k] ?? null) !== $v && !($v === null && !array_key_exists($k, $doc))) {
                return false;
            }
        }

        return true;
    }

    private function matchIndex(array $filter): ?int
    {
        foreach ($this->docs as $i => $doc) {
            if ($this->matches($doc, $filter)) {
                return $i;
            }
        }

        return null;
    }
}

/** Mirrors MongoCollection::findOne()'s cursor return contract (iterable). */
final class FakeCursor implements \IteratorAggregate
{
    public function __construct(private array $docs)
    {
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->docs);
    }
}

final class FakeInsertOneResult
{
    public function __construct(private mixed $insertedId)
    {
    }

    public function getInsertedId(): mixed
    {
        return $this->insertedId;
    }
}

final class FakeUpdateResult
{
    public function __construct(private int $modified)
    {
    }

    public function getModifiedCount(): int
    {
        return $this->modified;
    }
}

final class FakeDeleteResult
{
    public function __construct(private int $deleted)
    {
    }
}

/**
 * Resolver seam: name → FakeMongoCollection, cached per name (one fake per
 * collection, like one real collection per DB namespace). Wired via
 * `new MongoStore(fn(string $name) => $fakes->for($name))`. Untyped return:
 * MongoDB\Collection is final (no interface), so the store duck-types on
 * the method surface — production passes the real object, tests the fake.
 */
final class FakeMongoFactory
{
    /** @var array<string, FakeMongoCollection> */
    public array $collections = [];

    public function for(string $name): FakeMongoCollection
    {
        return $this->collections[$name] ??= new FakeMongoCollection($name);
    }
}