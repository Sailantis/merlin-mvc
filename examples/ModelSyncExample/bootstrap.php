<?php
/**
 * Bootstrap configuration for the ModelSyncExample project.
 *
 * Edit the database settings below to match your environment,
 * then run the console:
 *
 *   php console.php model-sync all app/Models --apply
 *   php console.php model-sync model app/Models/User.php --apply
 *   php console.php model-sync model User --apply
 *
 * To set up the database, run one of the SQL files in the sql/ directory:
 *
 *   SQLite:     sqlite3 sync_example.sqlite < sql/sqlite.sql
 *   MySQL:      mysql -u root -p mydb < sql/mysql.sql
 *   PostgreSQL: psql -U myuser -d mydb -f sql/postgresql.sql
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Merlin\AppContext;
use Merlin\Db\Database;
use Merlin\Core\ModelMapping;

// Use plural table names (e.g. 'users' instead of 'user').
ModelMapping::usePluralTableNames(true);

$ctx = AppContext::instance();

// =============================================================================
// Choose ONE of the database configurations below and uncomment it.
// =============================================================================

// --- SQLite (no server required, easiest for testing) ---
$ctx->dbManager()->set('default', new Database(
    dsn: 'sqlite:' . __DIR__ . '/sync_example.sqlite',
    user: '',
    pass: ''
));

// --- MySQL ---
// $ctx->dbManager()->set('default', new Database(
//     dsn:  'mysql:host=127.0.0.1;dbname=sync_example;charset=utf8mb4',
//     user: 'root',
//     pass: 'secret'
// ));

// --- PostgreSQL ---
// $ctx->dbManager()->set('default', new Database(
//     dsn:  'pgsql:host=127.0.0.1;dbname=sync_example',
//     user: 'postgres',
//     pass: 'secret'
// ));

return $ctx;
