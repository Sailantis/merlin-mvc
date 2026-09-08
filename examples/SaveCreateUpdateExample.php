<?php
namespace Azera\SaveCreateUpdateExample;

require_once __DIR__ . '/../vendor/autoload.php';

use Azera\AppContext;
use Azera\Db\Database;
use Azera\Db\ModelMapping;
use Azera\Orm\Model;

/**
 * Example: Using Model save(), create(), update(), delete() methods
 *
 * This example demonstrates how to work with model persistence:
 * - create() - Insert a new record
 * - update() - Update changed fields only
 * - save() - Insert or update intelligently
 * - delete() - Remove a record
 * - hasChanged() - Check if model has unsaved changes
 *
 * Runs self-contained on SQLite (no server required):
 *   php examples/SaveCreateUpdateExample.php
 */

ModelMapping::usePluralTableNames(true); // User → users, Post → posts

$dbFile = sys_get_temp_dir() . '/azera_save_example_' . getmypid() . '.sqlite';
@unlink($dbFile);

$pdo = new \PDO('sqlite:' . $dbFile);
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE users (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    username    TEXT NOT NULL,
    email       TEXT NOT NULL,
    status      TEXT DEFAULT "active",
    bio         TEXT,
    posts_count INTEGER DEFAULT 0,
    created_at  TEXT,
    updated_at  TEXT
)');
$pdo->exec('CREATE TABLE posts (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL,
    title        TEXT NOT NULL,
    content      TEXT,
    view_count   INTEGER DEFAULT 0,
    status       TEXT DEFAULT "draft",
    published_at TEXT,
    created_at   TEXT
)');
$pdo->exec('CREATE TABLE products (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,
    price       REAL DEFAULT 0,
    stock       INTEGER DEFAULT 0,
    sku         TEXT NOT NULL,
    description TEXT
)');

AppContext::instance()->dbManager()->set('default', new Database('sqlite:' . $dbFile, '', ''));

// ============================================================================
// Model Definitions
// ============================================================================

class User extends Model
{
    public int $id;
    public string $username;
    public string $email;
    public string $status = 'active';
    public ?string $bio = null;
    public int $posts_count = 0;
    public string $created_at;
    public string $updated_at;
}

class Post extends Model
{
    public int $id;
    public int $user_id;
    public string $title;
    public string $content;
    public int $view_count = 0;
    public string $status = 'draft';
    public string $published_at;
    public string $created_at;
}

class Product extends Model
{
    public int $id;
    public string $name;
    public float $price;
    public int $stock;
    public string $sku;
    public ?string $description = null;
}

// ============================================================================
// CREATE - Insert a new record
// ============================================================================

echo "=== CREATE - Insert New Records ===\n\n";

// 1. Create a simple user
$user = new User();
$user->username   = 'alice';
$user->email      = 'alice@example.com';
$user->status     = 'active';
$user->created_at = date('Y-m-d H:i:s');
$user->updated_at = date('Y-m-d H:i:s');

echo "Before create():\n";
echo "  ID: " . ($user->id ?? 'NOT SET') . "\n";
echo "  Has changes: " . ($user->hasChanged() ? 'yes' : 'no') . "\n";

if ($user->save()) {
    echo "\nAfter create():\n";
    echo "  ID: {$user->id}\n";
    echo "  Has changes: " . ($user->hasChanged() ? 'yes' : 'no') . "\n";
    echo "  User created successfully!\n";
}

echo "\n";

// 2. Create with partial data
$post = new Post();
$post->user_id      = $user->id;
$post->title        = 'My First Post';
$post->content      = 'This is the content of my first post.';
$post->status       = 'draft';
$post->created_at   = date('Y-m-d H:i:s');
$post->published_at = date('Y-m-d H:i:s');

$post->save();
echo "Post created with ID: {$post->id}\n";

echo "\n";

// 3. Create multiple products
$products = [
    ['name' => 'Laptop', 'price' => 1299.99, 'stock' => 10, 'sku' => 'LAP-001'],
    ['name' => 'Mouse', 'price' => 29.99, 'stock' => 100, 'sku' => 'MSE-001'],
    ['name' => 'Keyboard', 'price' => 99.99, 'stock' => 50, 'sku' => 'KBD-001'],
];

foreach ($products as $data) {
    $product = new Product();
    $product->name  = $data['name'];
    $product->price = $data['price'];
    $product->stock = $data['stock'];
    $product->sku   = $data['sku'];
    $product->save();
    echo "Product created: {$product->name} (ID: {$product->id})\n";
}

echo "\n\n";

// ============================================================================
// UPDATE - Modify and save changes only
// ============================================================================

echo "=== UPDATE - Modify Existing Records ===\n\n";

// 1. Load user and modify
$user = User::find($user->id);
echo "Loaded user: {$user->username}\n";
echo "Has changes: " . ($user->hasChanged() ? 'yes' : 'no') . "\n";

// Make a change
$user->email = 'alice.updated@example.com';
echo "\nAfter changing email:\n";
echo "Has changes: " . ($user->hasChanged() ? 'yes' : 'no') . "\n";

// Only the changed fields are updated
if ($user->save()) {
    echo "User updated successfully!\n";
    echo "Has changes: " . ($user->hasChanged() ? 'yes' : 'no') . "\n";
}

echo "\n";

// 2. Modify multiple fields
$post = Post::find($post->id);
echo "Original post status: {$post->status}\n";

// Make multiple changes
$post->title        = 'My First Post - Updated';
$post->content      = 'Updated content for my first post.';
$post->status       = 'published';
$post->published_at = date('Y-m-d H:i:s');

$post->save();
echo "Post updated: {$post->title} (status: {$post->status})\n";

echo "\n";

// 3. Increment counters
$post->view_count = ($post->view_count ?? 0) + 5;
$post->save();
echo "Post view count: {$post->view_count}\n";

echo "\n";

// 4. Conditional update - only update if changes exist
$user->bio = 'I am a developer.';
if ($user->save()) {
    echo "User bio updated: {$user->bio}\n";
} else {
    echo "No changes to update.\n";
}

echo "\n\n";

// ============================================================================
// SAVE - Intelligent insert or update
// ============================================================================

echo "=== SAVE - Insert or Update Intelligently ===\n\n";

// 1. Load existing user and save
$user = User::find($user->id);
$user->bio = 'Updated developer profile.';
$user->save();
echo "User saved (update): {$user->username}\n";

echo "\n";

// 2. Create and save new user
$newUser = new User();
$newUser->username   = 'bob';
$newUser->email      = 'bob@example.com';
$newUser->created_at = date('Y-m-d H:i:s');
$newUser->updated_at = date('Y-m-d H:i:s');

$newUser->save();
echo "New user saved (insert) with ID: {$newUser->id}\n";

echo "\n";

// 3. Modify and save
$newUser->email = 'bob.updated@example.com';
$newUser->save();
echo "User saved (update): {$newUser->email}\n";

echo "\n";

// 4. Save with no changes (returns false)
if (!$newUser->save()) {
    echo "No changes to save.\n";
}

echo "\n\n";

// ============================================================================
// DELETE - Remove records
// ============================================================================

echo "=== DELETE - Remove Records ===\n\n";

// 1. Load and delete
$userToDelete = User::findOne(['username' => 'bob']);
if ($userToDelete) {
    $username = $userToDelete->username;
    $userToDelete->delete();
    echo "User deleted: {$username}\n";
}

echo "\n";

// 2. Delete using builder (for bulk operations)
Post::query()
    ->where('status = :status', ['status' => 'draft'])
    ->delete();
echo "All draft posts deleted.\n";

echo "\n\n";

// ============================================================================
// CHANGE TRACKING - hasChanged()
// ============================================================================

echo "=== CHANGE TRACKING - Track Modifications ===\n\n";

// 1. Fresh instance has changes (not loaded)
$newProduct = new Product();
$newProduct->name  = 'Monitor';
$newProduct->price = 299.99;
$newProduct->stock = 20;
$newProduct->sku   = 'MON-001';

echo "New product has changes: " . ($newProduct->hasChanged() ? 'yes' : 'no') . "\n";

echo "\n";

// 2. Loaded instance has no changes initially
$product = Product::find(1);
echo "Loaded product has changes: " . ($product->hasChanged() ? 'yes' : 'no') . "\n";

$product->stock = 15;
echo "After modification has changes: " . ($product->hasChanged() ? 'yes' : 'no') . "\n";

echo "\n";

// 3. Use hasChanged() to prevent unnecessary updates
$user = User::find($user->id);

// Only update if something changed
if ($user->hasChanged()) {
    $user->save();
    echo "User had changes and was updated.\n";
} else {
    echo "User has no changes, skipping update.\n";
}

echo "\n";

// 4. Load state to reset changes
$user->email = 'test@example.com';
echo "Modified email, has changes: " . ($user->hasChanged() ? 'yes' : 'no') . "\n";

// Discard changes by reloading state
$user->loadState();
echo "After loadState(), has changes: " . ($user->hasChanged() ? 'yes' : 'no') . "\n";

echo "\n\n";

// ============================================================================
// WORKFLOW PATTERNS
// ============================================================================

echo "=== WORKFLOW PATTERNS ===\n\n";

// 1. Check and Update Pattern
echo "1. Check and Update Pattern:\n";
$existingProduct = Product::findOne(['sku' => 'LAP-001']);
if ($existingProduct) {
    echo "   Found existing product: {$existingProduct->name}\n";
    $existingProduct->stock = 12;
    $existingProduct->save();
    echo "   Stock updated to: {$existingProduct->stock}\n";
}

echo "\n";

// 2. Create if Not Exists Pattern
echo "2. Create if Not Exists Pattern:\n";
$product = Product::findOne(['sku' => 'SPC-001']);
if (!$product) {
    $product = new Product();
    $product->name  = 'Speaker';
    $product->price = 149.99;
    $product->stock = 30;
    $product->sku   = 'SPC-001';
    $product->save();
    echo "   Created new product: {$product->name}\n";
} else {
    echo "   Product already exists: {$product->name}\n";
}

echo "\n";

// 3. Load, Modify, Save Pattern
echo "3. Load, Modify, Save Pattern:\n";
$user = User::find($user->id);
$user->posts_count = ($user->posts_count ?? 0) + 1;
$user->updated_at  = date('Y-m-d H:i:s');
if ($user->save()) {
    echo "   User updated successfully.\n";
}

echo "\n";

// 4. Bulk Update Pattern using builders
echo "4. Bulk Update Pattern:\n";
User::query()
    ->set('status', 'inactive')
    ->where('created_at < :date', ['date' => '2024-01-01'])
    ->update();
echo "   Users before 2024-01-01 marked as inactive.\n";

echo "\n";

// 5. Safe Delete Pattern
echo "5. Safe Delete Pattern:\n";
if (User::exists(['username' => 'alice'])) {
    $user = User::findOne(['username' => 'alice']);
    $user->delete();
    echo "   Confirmed deletion of user: alice\n";
} else {
    echo "   User not found.\n";
}

echo "\n\n";

// ============================================================================
// ERROR HANDLING
// ============================================================================

echo "=== ERROR HANDLING ===\n\n";

// 1. save() WITHOUT an ID is an INSERT, not an error: with no identity to
//    adopt, the EntityManager schedules an INSERT and backfills the
//    auto-generated PK afterwards.
$newUser = new User();
$newUser->username   = 'orphan';
$newUser->email      = 'orphan@example.com';
$newUser->created_at = date('Y-m-d H:i:s');
$newUser->updated_at = date('Y-m-d H:i:s');
$newUser->save();
echo "Save without ID inserted the row; backfilled id: {$newUser->id}\n";

echo "\n";

try {
    // 2. delete() without an ID throws — there is no identity to target.
    $orphanPost = new Post();
    $orphanPost->user_id = 1;
    $orphanPost->title   = 'Orphan Post';
    $orphanPost->delete(); // RuntimeException: ID field 'id' not set
} catch (\RuntimeException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n\nExample completed.\n";