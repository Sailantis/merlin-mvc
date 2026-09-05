<?php

namespace Azera\Orm\Storage;

use Azera\Db\Database;
use Azera\Db\DatabaseManager;
use Azera\Orm\Metadata;

/**
 * SQL backend of the {@see Store} seam over a {@see Database}.
 *
 * BORROWS, never owns: holds read/write ROLE STRINGS and resolves the live
 * connection via DatabaseManager per operation â€” the same pattern as
 * Model::readConnection(). Consequences: QB + legacy save() + ORM flush all
 * share ONE connection per role (transactions join the caller's nesting);
 * per-call role resolution keeps dynamic tenancy; cost = one cached array
 * read. ALL SQL goes through the connection so Db events fire (tracking).
 *
 * Holds the RETURNING matrix: pk_set -> plain INSERT; all non-PK cols set +
 * driver RETURNING -> RETURNING id; unset non-PK cols -> RETURNING *;
 * no-RETURNING driver -> lastInsertId.
 */
final class PdoStore implements Store
{
    public function __construct(
        private DatabaseManager $dbm,
        private string $readRole = 'read',
        private string $writeRole = 'write',
    ) {}

    public function insertOne(string $class, array $data): array
    {
        $meta = Metadata::for($class);
        $db   = $this->dbm->getOrDefault($this->writeRole);

        // Strategy selection lives here (per-situation, not always RETURNING *).
        $strategy = $this->strategy($meta, $data, $db->supportsReturning());
        $set      = array_filter($data, fn($v) => $v !== null);

        [$sql, $params] = $this->insertSql($meta, $set);

        switch ($strategy) {
            case 'pk_set':
                $db->query($sql, $params);
                return ['row' => null, 'id' => null];

            case 'returning_id':
                $pk      = $this->pkColumn($meta);
                $row     = $db->selectRow($sql . ' RETURNING ' . $db->quoteIdentifier($pk['name']), $params, \PDO::FETCH_ASSOC);
                $idValue = $row[$pk['name']] ?? null;
                return ['row' => null, 'id' => $idValue];

            case 'returning_all':
                $row = $db->selectRow($sql . ' RETURNING *', $params, \PDO::FETCH_ASSOC);
                return ['row' => $row, 'id' => null];

            case 'last_insert_id':
            default:
                $db->query($sql, $params);
                $pk = $this->pkColumn($meta);
                $id = $db->lastInsertId();
                return ['row' => null, 'id' => ($id !== false && $id !== '0' && $id !== '') ? $id : null];
        }
    }

    public function updateOne(string $class, array $data, array $id): array
    {
        $meta = Metadata::for($class);
        $db   = $this->dbm->getOrDefault($this->writeRole);

        [$sql, $params] = $this->updateSql($meta, $data, $id);
        $db->query($sql, $params);

        return ['row' => null, 'id' => null];
    }

    public function upsertOne(string $class, array $data): array
    {
        $meta = Metadata::for($class);
        $db   = $this->dbm->getOrDefault($this->writeRole);

        // The PK is the CONFLICT TARGET — always caller-set on an upsert,
        // so strategy()'s matrix can't be reused here (it short-circuits
        // to pk_set before ever looking at non-PK columns). The only
        // interesting question: are non-PK columns missing? Their DB
        // defaults round-trip via RETURNING * (mirrors insertOne's
        // returning_all); with everything set, a plain statement is the
        // fastest shape. Non-RETURNING drivers: no backfill, same as
        // insertOne's last_insert_id fallback minus the id (upsert keeps
        // the caller's PK).
        $nonPkMissing = false;
        foreach ($meta['columns'] as $col) {
            if (!$col['pk'] && ($data[$col['name']] ?? null) === null) {
                $nonPkMissing = true;
                break;
            }
        }

        $set = array_filter($data, fn($v) => $v !== null);
        [$sql, $params] = $this->upsertSql($meta, $set, $db);

        if ($nonPkMissing && $db->supportsReturning()) {
            $row = $db->selectRow($sql . ' RETURNING *', $params, \PDO::FETCH_ASSOC);
            return ['row' => $row, 'id' => null];
        }

        $db->query($sql, $params);
        return ['row' => null, 'id' => null];
    }

    public function deleteOne(string $class, array $id): void
    {
        $meta = Metadata::for($class);
        $db   = $this->dbm->getOrDefault($this->writeRole);
        [$sql, $params] = $this->deleteSql($meta, $id);
        $db->query($sql, $params);
    }

    public function findBy(string $class, array $where): array
    {
        $meta = Metadata::for($class);
        $db   = $this->dbm->getOrDefault($this->readRole);
        [$sql, $params] = $this->selectSql($meta, $where);
        $rows = $db->selectAll($sql, $params, \PDO::FETCH_ASSOC);
        return $rows;
    }

    public function findByPk(string $class, array $id): ?array
    {
        $rows = $this->findBy($class, $id);
        return $rows[0] ?? null;
    }

    public function count(string $class, array $where = []): int
    {
        $meta = Metadata::for($class);
        $db   = $this->dbm->getOrDefault($this->readRole);
        [$sql, $params] = $this->countSql($meta, $where);
        $row = $db->selectRow($sql, $params, \PDO::FETCH_ASSOC);
        return (int) ($row['cnt'] ?? 0);
    }

    public function begin(): void
    {
        $this->dbm->getOrDefault($this->writeRole)->begin();
    }

    public function commit(): void
    {
        $this->dbm->getOrDefault($this->writeRole)->commit();
    }

    public function rollback(): void
    {
        $this->dbm->getOrDefault($this->writeRole)->rollback();
    }

    public function inTransaction(): bool
    {
        return $this->dbm->getOrDefault($this->writeRole)->inTransaction();
    }

    /* -------------------------------------------------- helpers */

    /**
     * RETURNING matrix (per-situation, NOT always RETURNING *):
     * pk_set -> plain INSERT; all non-PK cols set + driver RETURNING ->
     * RETURNING id; unset non-PK cols -> RETURNING *; no-RETURNING driver
     * -> lastInsertId.
     */
    private function strategy(array $meta, array $data, bool $supportsReturning): string
    {
        $pkCols = array_values(array_filter($meta['columns'], fn($c) => $c['pk']));
        if ($pkCols === []) {
            return 'last_insert_id';
        }

        $pkSet = true;
        foreach ($pkCols as $col) {
            if (($data[$col['name']] ?? null) === null) {
                $pkSet = false;
                break;
            }
        }

        if ($pkSet) {
            return 'pk_set';
        }

        if (!$supportsReturning) {
            return 'last_insert_id';
        }

        $nonPkNames = array_map(fn($c) => $c['name'], array_filter($meta['columns'], fn($c) => !$c['pk']));
        $missing    = array_diff($nonPkNames, array_keys(array_filter($data, fn($v) => $v !== null)));

        if ($missing !== []) {
            return 'returning_all';
        }

        // Single PK: RETURNING id is enough. Composite PK: every part
        // must backfill (RETURNING id would leave the other parts unset).
        return \count($pkCols) === 1 ? 'returning_id' : 'returning_all';
    }

    private function pkColumn(array $meta): array
    {
        foreach ($meta['columns'] as $col) {
            if ($col['pk']) {
                return $col;
            }
        }

        throw new \RuntimeException("No PK column in metadata for {$meta['class']}");
    }

    /**
     * Schema-qualified quoted table name from metadata
     * (#[Table(schema)] / schema() override — null schema yields the
     * bare table).
     */
    private function table(Database $db, array $meta): string
    {
        return $db->quoteIdentifier($meta['schema'] ?? null, $meta['source']);
    }

    private function insertSql(array $meta, array $set): array
    {
        $db   = $this->dbm->getOrDefault($this->writeRole);
        $cols = array_map(fn($c) => $db->quoteIdentifier($c), array_keys($set));
        $bind = array_fill(0, \count($set), '?');

        return [
            'INSERT INTO ' . $this->table($db, $meta)
            . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $bind) . ')',
            array_values($set),
        ];
    }

    /**
     * Single-statement UPSERT. The conflict target is the metadata PK.
     * The DO UPDATE SET writes NON-PK columns only, via excluded refs
     * ("col" = EXCLUDED."col") — the fast shape. Including the PK in SET
     * (or rebinding values instead of referencing EXCLUDED) forces SQLite
     * to compile the statement as an internal DELETE+INSERT, which is
     * fsync-bound (~1.2-5.7ms vs ~8µs per statement on WAL SQLite).
     *
     * Driver dialects: pgsql/sqlite use ON CONFLICT (target) DO UPDATE;
     * mysql/maria use ON DUPLICATE KEY UPDATE with VALUES() refs (their
     * only form; no conflict target). Null values in $set are dropped
     * upstream (same as insertOne) so DB defaults survive.
     */
    private function upsertSql(array $meta, array $set, Database $db): array
    {
        [$insertSql, $params] = $this->insertSql($meta, $set);

        $driver = $db->getDriver();

        if ($driver === 'mysql') {
            // MySQL has no conflict target and no EXCLUDED — VALUES(col) refs.
            $sets = [];
            foreach ($set as $col => $value) {
                if ($this->isPkColumn($meta, $col)) {
                    continue;
                }
                $sets[] = $db->quoteIdentifier($col) . ' = VALUES(' . $db->quoteIdentifier($col) . ')';
            }
            return [$insertSql . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $sets), $params];
        }

        // pgsql / sqlite: explicit conflict target + EXCLUDED refs.
        $pkCols = [];
        foreach ($meta['columns'] as $col) {
            if ($col['pk']) {
                $pkCols[] = $col['name'];
            }
        }

        $target = implode(', ', array_map(fn($c) => $db->quoteIdentifier($c), $pkCols));

        $sets = [];
        foreach ($set as $col => $value) {
            if ($this->isPkColumn($meta, $col)) {
                continue;
            }
            $sets[] = $db->quoteIdentifier($col) . ' = EXCLUDED.' . $db->quoteIdentifier($col);
        }

        return [$insertSql . ' ON CONFLICT (' . $target . ') DO UPDATE SET ' . implode(', ', $sets), $params];
    }

    private function isPkColumn(array $meta, string $colName): bool
    {
        foreach ($meta['columns'] as $col) {
            if ($col['pk'] && $col['name'] === $colName) {
                return true;
            }
        }

        return false;
    }

    private function updateSql(array $meta, array $data, array $id): array
    {
        $db   = $this->dbm->getOrDefault($this->writeRole);
        $sets = [];
        $bind = [];

        foreach ($data as $col => $value) {
            $sets[] = $db->quoteIdentifier($col) . ' = ?';
            $bind[] = $value;
        }

        $wheres = [];
        foreach ($meta['columns'] as $field => $col) {
            if ($col['pk'] && isset($id[$field])) {
                $wheres[] = $db->quoteIdentifier($col['name']) . ' = ?';
                $bind[] = $id[$field];
            }
        }

        return [
            'UPDATE ' . $this->table($db, $meta)
            . ' SET ' . implode(', ', $sets)
            . ' WHERE ' . implode(' AND ', $wheres ?: ['1=0']),
            $bind,
        ];
    }

    private function deleteSql(array $meta, array $id): array
    {
        $db     = $this->dbm->getOrDefault($this->writeRole);
        $wheres = [];
        $bind   = [];

        foreach ($meta['columns'] as $field => $col) {
            if ($col['pk'] && isset($id[$field])) {
                $wheres[] = $db->quoteIdentifier($col['name']) . ' = ?';
                $bind[] = $id[$field];
            }
        }

        return [
            'DELETE FROM ' . $this->table($db, $meta)
            . ' WHERE ' . implode(' AND ', $wheres ?: ['1=0']),
            $bind,
        ];
    }

    private function selectSql(array $meta, array $where): array
    {
        $db     = $this->dbm->getOrDefault($this->readRole);
        $wheres = [];
        $bind   = [];

        foreach ($where as $field => $value) {
            $colName = $meta['columns'][$field]['name'] ?? $field;
            $wheres[] = $db->quoteIdentifier($colName) . ' = ?';
            $bind[] = $value;
        }

        $sql = 'SELECT * FROM ' . $this->table($db, $meta)
            . ($wheres === [] ? '' : ' WHERE ' . implode(' AND ', $wheres));

        return [$sql, $bind];
    }

    private function countSql(array $meta, array $where): array
    {
        [$sql, $bind] = $this->selectSql($meta, $where);
        $sql = str_replace('SELECT *', 'SELECT COUNT(*) AS cnt', $sql);

        return [$sql, $bind];
    }
}