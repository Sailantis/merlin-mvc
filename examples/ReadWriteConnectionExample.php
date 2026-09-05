<?php
namespace Azera\ReadWriteConnectionExample;

require_once __DIR__ . '/../vendor/autoload.php';

use Azera\AppContext;
use Azera\Db\Database;
use Azera\Orm\Model;

/**
 * Example: Setting up read/write database connections
 * 
 * This example demonstrates how to configure separate read and write database connections.
 * This is useful for read-replica setups where you want to route read queries to replica servers
 * and write queries to the primary server.
 */

// ============================================================================
// Basic Setup: Single Database Connection
// ============================================================================
$dbManager = AppContext::instance()->dbManager();

// For simple setups, just create a single connection - it will be used for both reads and writes
$dbManager->set('default', fn() => new Database(
    'mysql:host=localhost;dbname=myapp',
    'root',
    'secret'
));

// This connection is automatically set as the default instance
// All queries will use this connection


// ============================================================================
// Advanced Setup: Read Replica Configuration
// ============================================================================

// 1. Create the primary (write) connection
$dbManager->set('write', fn() => new Database(
    'mysql:host=primary.example.com;dbname=myapp',
    'root',
    'secret'
));

// 2. Create the read replica connection
$dbManager->set('read', fn() => new Database(
    'mysql:host=replica.example.com;dbname=myapp',
    'readonly',
    'secret'
));

// Now queries are automatically routed:
// - SELECT queries → read replica
// - INSERT/UPDATE/DELETE → primary database


// ============================================================================
// Usage Examples with Model
// ============================================================================

class User extends Model
{
    public int $id;
    public string $username;
    public string $email;
    public string $status;
}

// All load methods automatically use the read connection
$user = User::find(123);                           // → read replica
$user = User::findOne(['email' => 'john@example.com']); // → read replica
$users = User::findAll(['status' => 'active']);         // → read replica
$exists = User::exists(['username' => 'alice']);        // → read replica
$count = User::count(['status' => 'active']);           // → read replica

// Write operations automatically use the write connection
$user = new User();
$user->username = 'bob';
$user->email = 'bob@example.com';
$user->save();  // → primary database

$user->status = 'inactive';
$user->update();  // → primary database

$user->delete();  // → primary database


// ============================================================================
// Per-Model Connection Configuration
// ============================================================================

class AnalyticsEvent extends Model
{
    public int $id;
    public string $event_name;
    public string $data;
}

// Configure per-model roles (static methods)
AnalyticsEvent::setDefaultRole('analytics');
//AnalyticsEvent::setDefaultReadRole('analytics_read');
//AnalyticsEvent::setDefaultWriteRole('analytics_write');

// This model now uses its own database connections
$event = AnalyticsEvent::find(456);  // → analytics replica
$event->save();                         // → analytics primary

// Other models still use global Database connections
$user = User::find(123);  // → global read connection
$user->update();               // → global write connection


// ============================================================================
// Advanced: Override Connection Methods for Dynamic Logic
// ============================================================================

class TenantData extends Model
{
    public int $id;
    public int $tenant_id;
    public string $data;

    /**
     * Override for complex connection logic (e.g., tenant-based routing)
     */
    public function readConnection(): Database
    {
        // Example: Route based on tenant_id if set
        if (isset($this->tenant_id) && $this->tenant_id > 1000) {
            // Use tenant-specific database
            static $tenantDb;
            if (!$tenantDb) {
                $tenantDb = new Database(
                    'mysql:host=tenant-db.example.com;dbname=tenant_' . $this->tenant_id,
                    'readonly',
                    'secret'
                );
            }
            return $tenantDb;
        }

        // Default fallback (uses per-model static or Database global)
        return parent::readConnection();
    }
}
