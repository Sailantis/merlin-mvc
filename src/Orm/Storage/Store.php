<?php

namespace Azera\Orm\Storage;

/**
 * Persistence-level seam between the ORM and any storage backend.
 *
 * Operations the EntityManager's write pipeline performs — NOT a query builder. SQL stores
 * implement it over a {@see \Azera\Db\Database}; Mongo over the
 * mongodb library. The per-situation write strategies (RETURNING matrix)
 * live in each backend. A model belongs to exactly one store, declared in
 * metadata (store: 'sql' | 'mongo' + storeRole).
 */
interface Store
{
    /**
     * Persist one entity: INSERT or UPDATE (upsert when flagged).
     * Returns raw row(s) for backfill: ['row' => ?array, 'id' => int|string|null].
     *
     * @param array<string, mixed> $data column-name-keyed raw values
     * @return array{row: ?array, id: int|string|null}
     */
    public function insertOne(string $class, array $data): array;

    /**
     * Update one entity by PK values.
     *
     * @param array<string, mixed> $data    column-name-keyed changed values
     * @param array<string, mixed> $id      PK field => value
     * @return array{row: ?array, id: int|string|null}
     */
    public function updateOne(string $class, array $data, array $id): array;

    /**
     * Atomic UPSERT: INSERT ... ON CONFLICT DO UPDATE (SQL) / updateOne
     * with upsert:true (Mongo). The caller-set PK is the conflict target —
     * $data must carry every PK column. Existence is resolved BY THE
     * DATABASE at write time: no prior SELECT, no insert-or-update guess.
     * Returns raw row(s) for backfill, same contract as insertOne
     * (RETURNING * when unset non-PK columns should refresh DB defaults).
     *
     * @param array<string, mixed> $data column-name-keyed raw values (PK included)
     * @return array{row: ?array, id: int|string|null}
     */
    public function upsertOne(string $class, array $data): array;

    /**
     * Delete one entity by PK values.
     * @param array<string, mixed> $id PK field => value
     */
    public function deleteOne(string $class, array $id): void;

    /**
     * Read raw rows. Returns plain assoc rows (no ResultSet).
     *
     * @param class-string $class
     * @param array $where    PK field => value, or field                          => value
     * @return list<array<string, mixed>>
     */
    public function findBy(string $class, array $where): array;

    /**
     * Read one raw row by PK values (null when missing).
     *
     * @param class-string $class
     * @param array<string, mixed> $id PK field => value
     */
    public function findByPk(string $class, array $id): ?array;

    /**
     * Count matching rows.
     * @param array $where field => value
     */
    public function count(string $class, array $where = []): int;

    /* --------------------------------------------------- transactions */

    public function begin(): void;
    public function commit(): void;
    public function rollback(): void;

    /**
     * Whether a transaction (or savepoint level) is active.
     */
    public function inTransaction(): bool;
}
