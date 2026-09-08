<?php
namespace Azera\BulkWritesExample;

require_once __DIR__ . '/../vendor/autoload.php';

use Azera\AppContext;
use Azera\Db\Database;
use Azera\Db\ModelMapping;
use Azera\Orm\Model;

/**
 * Example: Bulk writes — many rows in few statements
 *
 * Three tiers, from fastest to most expressive:
 *
 * 1. Multi-row INSERT via the query builder
 *    Query::bulkValues([...rows...])->insert() — ONE round trip for the
 *    whole batch. Best for importing data you don't need back as objects.
 *
 * 2. Multi-row UPSERT
 *    bulkValues() + conflict() + updateValues([...]) + upsert() — one
 *    statement per batch. NOTE: multi-row upsert REQUIRES an explicit
 *    updateValues() column list (the EXCLUDED-mode shape); a single-row
 *    upsert may omit it (the derived shape).
 *
 * 3. Unit-of-work batching through the EntityManager
 *    persist() many entities, flush() ONCE — one transaction, N prepared
 *    statements, topologically ordered (owners before dependents). Best
 *    when you need the entities back (ids backfilled), change tracking,
 *    or mixed inserts/updates/deletes in one atomic commit.
 *
 * Rule of thumb: builder bulk = max throughput, no identity; EM flush =
 * throughput + identity + atomicity. NEVER persist()+flush() in a loop.
 *
 * Run with a self-contained SQLite database (no server required):
 *
 *   php examples/BulkWritesExample.php
 */

// ---------------------------------------------------------------------------
// Setup: self-contained SQLite database
// ---------------------------------------------------------------------------

ModelMapping::usePluralTableNames(true);

$dbFile = sys_get_temp_dir() . '/azera_bulk_example_' . getmypid() . '.sqlite';
@unlink($dbFile);

$pdo = new \PDO('sqlite:' . $dbFile);
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE products (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    sku      TEXT NOT NULL,
    name     TEXT NOT NULL,
    price    REAL DEFAULT 0,
    stock    INTEGER DEFAULT 0
)');

AppContext::instance()->dbManager()->set('default', new Database('sqlite:' . $dbFile, '', ''));

class Product extends Model
{
    public int $id;
    public string $sku;
    public string $name;
    public float $price;
    public int $stock;
}

// ============================================================================
// Tier 1: Multi-row INSERT (one statement for the whole batch)
// ============================================================================

echo "=== bulkValues() + insert() ===\n\n";

$rows = [
    ['sku' => 'LAP-001', 'name' => 'Laptop', 'price' => 1299.99, 'stock' => 10],
    ['sku' => 'MSE-001', 'name' => 'Mouse', 'price' => 29.99, 'stock' => 100],
    ['sku' => 'KBD-001', 'name' => 'Keyboard', 'price' => 99.99, 'stock' => 50],
    ['sku' => 'MON-001', 'name' => 'Monitor', 'price' => 299.99, 'stock' => 25],
];

\Azera\Db\Query::raw()->table('products')->bulkValues($rows)->insert();

echo "Inserted " . count($rows) . " rows in ONE statement. Total: " . Product::count() . "\n\n";

// Values can also come from any iterable (CSV import, API payload, ...):
$import = [
    ['sku' => 'SPK-001', 'name' => 'Speaker', 'price' => 149.99, 'stock' => 30],
    ['sku' => 'CAM-001', 'name' => 'Webcam', 'price' => 59.99, 'stock' => 0],
];
\Azera\Db\Query::raw()->table('products')->bulkValues($import)->insert();
echo "Second batch done. Total: " . Product::count() . "\n\n";

// ============================================================================
// Tier 2: Multi-row UPSERT (one statement, insert-or-update per row)
// ============================================================================

echo "=== bulkValues() + conflict() + updateValues() + upsert() ===\n\n";

// Row 1 already exists (LAP-001 gets id 1): its stock is UPDATED.
// Row 3 is new: it is INSERTED. One statement resolves both.
$upsertRows = [
    ['sku' => 'LAP-001', 'name' => 'Laptop', 'price' => 1199.99, 'stock' => 8],
    ['sku' => 'MSE-001', 'name' => 'Mouse', 'price' => 29.99, 'stock' => 95],
    ['sku' => 'PSU-001', 'name' => 'Power Supply', 'price' => 79.99, 'stock' => 15],
];

// sku is the natural/conflict key here; every non-key column listed in
// updateValues() is refreshed from the new row (EXCLUDED refs).
\Azera\Db\Query::raw()->table('products')
    ->bulkValues($upsertRows)
    ->conflict(['sku'])
    ->updateValues(['name', 'price', 'stock'])
    ->upsert();

echo "Upserted 3 rows: 2 updated, 1 inserted. Total: " . Product::count() . "\n";
echo "Laptop price now: " . Product::findOne(['sku' => 'LAP-001'])->price . "\n";
echo "Power Supply inserted: " . var_export(Product::exists(['sku' => 'PSU-001']), true) . "\n\n";

// The database id is the conflict target when present (schema PK upsert):
\Azera\Db\Query::raw()->table('products')
    ->bulkValues([
        ['id' => 1, 'sku' => 'LAP-001', 'name' => 'Laptop Pro', 'price' => 1399.99, 'stock' => 5],
    ])
    ->conflict(['id'])
    ->updateValues(['name', 'price', 'stock'])
    ->upsert();
echo "PK upsert: id 1 is now " . Product::find(1)->name . "\n\n";

// ============================================================================
// Tier 3: Unit-of-work batching through the EntityManager
// ============================================================================

echo "=== EM batching: persist many, flush ONCE ===\n\n";

$em = AppContext::instance()->entityManager();

// Build the batch in memory — nothing has hit the database yet.
$newProducts = [];
foreach ([
    ['CBL-001', 'USB-C Cable', 19.99, 200],
    ['HDM-001', 'HDMI Cable',  12.99, 150],
    ['DOCK-01', 'USB Dock',    89.99, 40],
] as [$sku, $name, $price, $stock]) {
    $p = new Product();
    $p->sku   = $sku;
    $p->name  = $name;
    $p->price = $price;
    $p->stock = $stock;
    $newProducts[] = $p;
    $em->persist($p);
}

// ONE flush = one transaction, three INSERTs, each id backfilled.
$em->flush();

echo "Flushed " . count($newProducts) . " INSERTs in one transaction:\n";
foreach ($newProducts as $p) {
    echo "  - {$p->sku}: id={$p->id}, name={$p->name}\n";
}
echo "\n";

// Mixed batch: inserts + updates + deletes are scheduled together and
// executed atomically — a failure rolls the whole batch back.
$doomed  = $newProducts[2]; // delete this one
$stocked = $newProducts[0]; // update this one
$extra   = new Product();   // insert this one
$extra->sku   = 'SSD-001';
$extra->name  = 'SSD 1TB';
$extra->price = 99.99;
$extra->stock = 60;

$stocked->stock = 195; // dirty diff → UPDATE stock only
$em->persist($stocked);
$em->remove($doomed);
$em->persist($extra);

$em->flush(); // all three effects commit together

echo "Mixed batch committed: stocked={$stocked->stock}, extra id={$extra->id}, "
    . "dock deleted=" . var_export(Product::exists(['sku' => 'DOCK-01']), true) . "\n\n";

// ============================================================================
// The anti-pattern: flush inside the loop
// ============================================================================

echo "=== Anti-pattern vs batching ===\n\n";

// WRONG: one transaction + round trip PER row (N tx, N statements):
//   foreach ($rows as $row) { $em->persist($row); $em->flush(); }
//
// RIGHT: schedule everything, flush once (1 tx, N statements):
$batch = [];
foreach ([['TAG-01', 'Tag A'], ['TAG-02', 'Tag B'], ['TAG-03', 'Tag C']] as $i => [$sku, $name]) {
    $p = new Product();
    $p->sku   = $sku;
    $p->name  = $name;
    $p->price = 1.0 + $i;
    $p->stock = 10;
    $batch[] = $p;
    $em->persist($p);
}
$em->flush(); // exactly one flush for the whole batch

echo "Batched flush backfilled ids: "
    . implode(', ', array_map(fn($p) => $p->id, $batch)) . "\n\n";

// ============================================================================
// Update-by-criteria: set one value for MANY rows in a single statement
// ============================================================================

echo "=== Set-based UPDATE ===\n\n";

// Not a row-at-a-time loop — one UPDATE statement:
Product::query()
    ->set('stock', 0)
    ->where('stock = :out', ['out' => 0])
    ->update();

\Azera\Db\Query::raw()->table('products')
    ->set('stock', 5)
    ->where('sku = :sku', ['sku' => 'CAM-001'])
    ->update();

echo "Webcam stock after set-based update: " . Product::findOne(['sku' => 'CAM-001'])->stock . "\n";
echo "Total products at end: " . Product::count() . "\n\nExample completed.\n";