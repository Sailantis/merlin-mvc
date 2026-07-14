<?php
namespace Merlin\CompositeKeyExamples;

/**
 * Example Models demonstrating composite key support
 * 
 * This file shows various use cases for the refactored Model class
 */

namespace Examples;

use Merlin\Core\Model;

// ============================================================================
// Example 1: Simple Single-Key Model (Unchanged Behavior)
// ============================================================================

class User extends Model
{
    public int $id;
    public string $username;
    public string $email;
    public ?string $bio = null;
    public string $created_at;

    // No need to override anything - defaults to ['id']
}

// Usage:
$user = new User();
$user->username = 'johndoe';
$user->email = 'john@example.com';
$user->save();  // Auto-populates $user->id
echo "Created user with ID: {$user->id}\n";

$user->email = 'newemail@example.com';
$user->update();  // WHERE (id = ?)

// ============================================================================
// Example 2: Composite Primary Key
// ============================================================================

class UserProduct extends Model
{
    public int $user_id;
    public int $product_id;
    public int $quantity;
    public float $price;
    public string $added_at;

    public function idFields(): array
    {
        return ['user_id', 'product_id'];
    }
}

// Usage:
$up = new UserProduct();
$up->user_id = 10;
$up->product_id = 25;
$up->quantity = 5;
$up->price = 99.99;
$up->save();

// Update
$up->quantity = 10;
$up->update();  // WHERE (user_id = 10) AND (product_id = 25)

// Delete
$up->delete();  // WHERE (user_id = 10) AND (product_id = 25)

// ============================================================================
// Example 3: Multi-Tenant with Composite Keys
// ============================================================================

class TenantData extends Model
{
    public int $tenant_id;
    public int $id;
    public string $data;
    public string $created_at;

    public function idFields(): array
    {
        return ['tenant_id', 'id'];
    }

    public function source(): string
    {
        return 'tenant_data';
    }
}

// PostgreSQL: Both tenant_id and id can be auto-generated
$data = new TenantData();
$data->data = 'test data';
$data->save();  // RETURNING * populates both tenant_id and id
echo "Created with tenant_id: {$data->tenant_id}, id: {$data->id}\n";

// MySQL/SQLite: Composite keys must be pre-set
$data = new TenantData();
$data->tenant_id = 5;
$data->id = 100;
$data->data = 'test data';
$data->save();

// ============================================================================
// Example 4: Dynamic Table Names (Language-Specific)
// ============================================================================

class TextModel extends Model
{
    public int $id;
    public string $key;
    public string $value;

    public function source(): string
    {
        // Returns: text_en, text_de, text_fr, etc.
        return 'text_' . ($GLOBALS['lang'] ?? 'en');
    }
}

// Set language
$GLOBALS['lang'] = 'de';

$text = new TextModel();
$text->key = 'welcome';
$text->value = 'Willkommen!';
$text->save();  // Inserts into 'text_de' table

// Query from German table
$texts = TextModel::query()
    ->where('key', 'welcome')
    ->select();

// ============================================================================
// Example 5: Read/Write Connection Separation (Master-Replica)
// ============================================================================

class Product extends Model
{
    public int $id;
    public string $name;
    public float $price;
    public int $stock;
}

// Automatically uses correct connection
$products = Product::query()  // Uses readConnection()
    ->where('stock >', 0)
    ->select();

/**
 * @var Product $product
 */
$product = $products->firstModel();
$product->stock -= 1;
$product->update();  // Uses writeConnection()

// ============================================================================
// Example 6: State Tracking for Efficient Updates
// ============================================================================

class Order extends Model
{
    public int $id;
    public string $status;
    public float $total;
    public string $updated_at;
}

// Fetch with query builder
$order = Order::query()
    ->where('id', 100)
    ->select()
    ->firstModel();

// Model tracks original state
$order->status = 'shipped';
$order->total = 150.00;

// Only sends changed fields to database
$order->update();  // UPDATE orders SET status = ?, total = ? WHERE id = ?

// Check if model has changed
if ($order->hasChanged()) {
    echo "Order has unsaved changes\n";
}

// ============================================================================
// Example 7: UPSERT with Composite Keys
// ============================================================================

class CartItem extends Model
{
    public int $user_id;
    public int $product_id;
    public int $quantity;

    public function idFields(): array
    {
        return ['user_id', 'product_id'];
    }
}

$item = new CartItem();
$item->user_id = 10;
$item->product_id = 25;
$item->quantity = 1;

// First call: INSERT
$item->save();

// Modify
$item->quantity = 5;

// Second call: UPDATE (because state exists and IDs are set)
$item->save();  // UPDATE ... WHERE (user_id = 10) AND (product_id = 25)

// ============================================================================
// Example 8: Batch Operations with Builder
// ============================================================================

class Activity extends Model
{
    public int $id;
    public int $user_id;
    public string $action;
    public string $created_at;
}

// Delete all activities for a user
Activity::query()
    ->where('user_id', 10)
    ->where('created_at <', '2023-01-01')
    ->delete();

// Update multiple records
Activity::query()
    ->values(['action' => 'archived'])
    ->where('user_id', 10)
    ->where('created_at <', '2023-01-01')
    ->update();

// Complex query with joins
$activities = Activity::query('a')
    ->join('users', 'u', 'a.user_id = u.id')
    ->columns(['a.*', 'u.username'])
    ->where('a.action', 'login')
    ->orderBy(['a.created_at', 'DESC'])
    ->limit(10)
    ->select();

// ============================================================================
// Example 9: Error Handling
// ============================================================================

try {
    $item = new UserProduct();
    $item->user_id = 10;
    // product_id not set
    $item->update();
} catch (\Merlin\Db\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    // Output: ID field(s) UserProduct->{'product_id'} not set
}

try {
    $item = new UserProduct();
    $item->quantity = 5;
    // Both keys missing on MySQL
    $item->save();
} catch (\Merlin\Db\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    // Output: Composite key fields UserProduct->{'user_id', 'product_id'} must be set for MySQL/SQLite
}
