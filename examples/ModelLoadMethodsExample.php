<?php
namespace Azera\ModelLoadMethodsExample;

require_once __DIR__ . '/../vendor/autoload.php';

use Azera\AppContext;
use Azera\Db\Database;
use Azera\Db\ResultSet;
use Azera\Orm\Model;

/**
 * Example: Using Model load methods
 *
 * This example demonstrates convenience methods for loading models:
 * - find() - Load by primary key (single or composite)
 * - findOne() - Load one record by conditions
 * - findAll() - Load multiple records by conditions
 * - exists() - Check if record exists
 * - count() - Count matching records
 */

// Setup database connection
$db = new Database(
    'mysql:host=localhost;dbname=myapp',
    'root',
    'secret'
);

AppContext::instance()->dbManager()->set('default', $db);

// ============================================================================
// Model Definitions
// ============================================================================

class User extends Model
{
    public int $id;
    public string $username;
    public string $email;
    public string $status;

    public function test() {}
}

class UserProduct extends Model
{
    public int $user_id;
    public int $product_id;
    public int $quantity;
    public string $created_at;

    // Composite primary key
    public function idFields(): array
    {
        return ['user_id', 'product_id'];
    }
}

class CartItem extends Model
{
    public int $cart_id;
    public int $product_id;
    public int $user_id;
    public int $quantity;

    // Composite primary key with 3 fields
    public function idFields(): array
    {
        return ['cart_id', 'product_id', 'user_id'];
    }
}

// ============================================================================
// find() - Single Primary Key
// ============================================================================

// Load user by ID (scalar value)
$user = User::find(10);
if ($user !== null) {
    $user->test();
    echo "Found user: {$user->username}\n";

    // State is automatically saved on load
    echo "Has changes: " . ($user->hasChanged() ? 'yes' : 'no') . "\n"; // no

    $user->email = 'newemail@example.com';
    echo "Has changes: " . ($user->hasChanged() ? 'yes' : 'no') . "\n"; // yes
} else {
    echo "User not found\n";
}

// ============================================================================
// find() - Composite Primary Key (Positional Array)
// ============================================================================

// Load by composite key using positional array
// Values are mapped to idFields() in order: [user_id, product_id]
$userProduct = UserProduct::find([10, 25]);
if ($userProduct !== null) {
    echo "Found user-product: User {$userProduct->user_id}, Product {$userProduct->product_id}\n";
    echo "Quantity: {$userProduct->quantity}\n";
}

// ============================================================================
// find() - Composite Primary Key (Associative Array)
// ============================================================================

// Load by composite key using associative array (explicit field names)
$userProduct = UserProduct::find(
    [
        'user_id'    => 10,
        'product_id' => 25
    ]
);

// Three-field composite key example
$cartItem = CartItem::find(
    [
        'cart_id'    => 1,
        'product_id' => 25,
        'user_id'    => 10
    ]
);

// Or using positional array
$cartItem = CartItem::find([1, 25, 10]); // Maps to [cart_id, product_id, user_id]

$user = User::findOne(['email' => 'john@example.com']);
if ($user !== null) {
    echo "Found user by email: {$user->username}\n";
}

// Load by multiple conditions
$user = User::findOne(
    [
        'username' => 'alice',
        'status'   => 'active'
    ]
);

// Returns null if not found
$user = User::findOne(['email' => 'nonexistent@example.com']);
if ($user === null) {
    echo "User not found\n";
}

// ============================================================================
// findAll() - Load Multiple Records
// ============================================================================

// Load all active users
$users = User::findAll(['status' => 'active']);
echo "Found " . count($users) . " active users\n";

foreach ($users as $user) {
    echo "- {$user->username} ({$user->email})\n";

    // State is automatically saved for each model
    echo "  Has changes: " . ($user->hasChanged() ? 'yes' : 'no') . "\n";
}

// Load all records (no conditions)
$allUsers = User::findAll();
echo "Total users: " . count($allUsers) . "\n";

// Load with multiple conditions
$userProducts = UserProduct::findAll(
    [
        'user_id'  => 10,
        'quantity' => 0 // Find items with zero quantity
    ]
);

// ============================================================================
// exists() - Check Existence Without Loading
// ============================================================================

// Check if user exists by email
if (User::exists(['email' => 'john@example.com'])) {
    echo "User with this email already exists\n";
} else {
    echo "Email is available\n";
}

// Check by multiple conditions
if (User::exists(['username' => 'alice', 'status' => 'active'])) {
    echo "Active user 'alice' exists\n";
}

// Check composite key existence
if (UserProduct::exists(['user_id' => 10, 'product_id' => 25])) {
    echo "User 10 has product 25\n";
}

// ============================================================================
// count() - Count Records
// ============================================================================

// Count all users
$totalUsers = User::count();
echo "Total users: {$totalUsers}\n";

// Count by condition
$activeUsers = User::count(['status' => 'active']);
echo "Active users: {$activeUsers}\n";

// Count by multiple conditions
$count = UserProduct::count(
    [
        'user_id'  => 10,
        'quantity' => 0
    ]
);
echo "User 10 has {$count} products with zero quantity\n";

// ============================================================================
// Combining Load Methods with Updates
// ============================================================================

// Load, modify, and update
$user = User::find(10);
if ($user !== null) {
    echo "Original email: {$user->email}\n";

    $user->email  = 'updated@example.com';
    $user->status = 'active';

    if ($user->update()) {
        echo "User updated successfully\n";
    }

    // Can also use save() which handles insert or update
    $user->username = 'newname';
    $user->save();
}

// ============================================================================
// Load and Delete
// ============================================================================

$user = User::find(999);
if ($user !== null) {
    $user->delete();
    echo "User deleted\n";
}

// ============================================================================
// Working with findAll() results
// ============================================================================

$users = User::findAll(['status' => 'active']);

// findAll() returns a plain list of heap-tracked entities — iterate directly
foreach ($users as $user) {
    // Process each user
}

// Get first
$firstUser = $users[0] ?? null;

// The list IS the array of models
$userArray = $users;

// Count
count($users);

// ============================================================================
// Error Handling
// ============================================================================

try {
    // Load with wrong number of ID values for composite key
    $userProduct = UserProduct::find([10]); // Only 1 value, needs 2
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    // "ID array count mismatch for UserProduct: expected 2, got 1"
}

// ============================================================================
// Comparison with Builder Pattern
// ============================================================================

// Old way: using selectBuilder
$user = User::query()
    ->where('id', 10)
    ->firstEntity();

// New way: using find()
$user = User::find(10);

// Old way: checking existence
$exists = User::query()
    ->columns(['1'])
    ->where('email', 'test@example.com')
    ->select()
    ->fetchColumn() !== null;

// New way: using exists
$exists = User::exists(['email' => 'test@example.com']);

// Old way: counting
$count = User::query()
    ->columns(['COUNT(*) as count'])
    ->where('status', 'active')
    ->select()
    ->fetchColumn();

// New way: using count
$count = User::count(['status' => 'active']);

// Note: For complex queries, you can still use the builder pattern
$user = User::query()
    ->where('status', 'active')
    ->where('created_at', '2024-01-01')
    ->orderBy('username ASC')
    ->limit(10)
    ->firstEntity();