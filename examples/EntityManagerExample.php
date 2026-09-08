<?php
namespace Azera\EntityManagerExample;

require_once __DIR__ . '/../vendor/autoload.php';

use Azera\AppContext;
use Azera\Db\Database;
use Azera\Db\ModelMapping;
use Azera\Orm\Model;

/**
 * Example: Working with the EntityManager (EM) directly
 *
 * Model::save()/find() are a facade over the EntityManager. Dropping to the
 * EM gives you the explicit unit-of-work workflow:
 *
 * - persist()  - Queue an INSERT (or UPDATE for managed entities)
 * - remove()   - Queue a DELETE
 * - upsert()   - Queue an atomic INSERT ... ON CONFLICT DO UPDATE
 * - flush()    - Execute ALL scheduled writes in ONE transaction
 * - find()     - Identity-mapped read (heap probe first, store on miss)
 * - adopt()/track() - Register externally-loaded entities as managed
 * - contains()/isScheduled()/isDirty()/dirtyData()/revert()
 *
 * Key semantics demonstrated below:
 * - Nothing hits the database until flush() — persist() only schedules.
 * - One flush() = one transaction (owners are ordered before dependents
 *   so auto-generated owner PKs backfill into dependents' FK values).
 * - The identity map: the same row read twice yields the SAME instance.
 * - Entities loaded through EM reads are diff-tracked: persist() after a
 *   change schedules an UPDATE of only the changed columns.
 *
 * Run with a self-contained SQLite database (no server required):
 *
 *   php examples/EntityManagerExample.php
 */

// ---------------------------------------------------------------------------
// Setup: self-contained SQLite database
// ---------------------------------------------------------------------------

ModelMapping::usePluralTableNames(true); // User → users, Post → posts

$dbFile = sys_get_temp_dir() . '/azera_em_example_' . getmypid() . '.sqlite';
@unlink($dbFile);

$pdo = new \PDO('sqlite:' . $dbFile);
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE users (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    email    TEXT NOT NULL,
    status   TEXT DEFAULT "active",
    posts_count INTEGER DEFAULT 0
)');
$pdo->exec('CREATE TABLE posts (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id  INTEGER NOT NULL,
    title    TEXT NOT NULL,
    status   TEXT DEFAULT "draft"
)');

AppContext::instance()->dbManager()->set('default', new Database('sqlite:' . $dbFile, '', ''));

// ---------------------------------------------------------------------------
// Model definitions
// ---------------------------------------------------------------------------

class User extends Model
{
    public int $id;
    public string $username;
    public string $email;
    public string $status;
    public int $posts_count = 0;

    // Relation declarations (used by flush() ordering + eager loading)
    #[\Azera\Orm\Attribute\HasMany(target: Post::class)]
    public $posts;
}

class Post extends Model
{
    public int $id;
    public int $user_id;
    public string $title;
    public string $status;

    #[\Azera\Orm\Attribute\BelongsTo(target: User::class)]
    public $author;
}

$em = AppContext::instance()->entityManager();

// ============================================================================
// persist() + flush() — the explicit write workflow
// ============================================================================

echo "=== persist() schedules, flush() writes ===\n\n";

$user = new User();
$user->username = 'alice';
$user->email    = 'alice@example.com';
$user->status   = 'active';

$em->persist($user);

// Nothing has touched the database yet — persist() only schedules:
echo "Contains: " . var_export($em->contains($user), true) . "\n";     // true
echo "Scheduled: " . var_export($em->isScheduled($user), true) . "\n"; // true
echo "New user id set: " . var_export(isset($user->id), true) . "\n";  // false

$em->flush(); // INSERT runs here, inside one transaction

echo "After flush: id={$user->id}, scheduled: "
    . var_export($em->isScheduled($user), true) . "\n\n";

// persist() on a managed entity with modifications schedules an UPDATE
// of ONLY the changed columns (diff against the heap node snapshot):
$user->email = 'alice.updated@example.com';
$em->persist($user)->flush();

// ============================================================================
// Identity map — one row, one object
// ============================================================================

echo "=== Identity map ===\n\n";

$again = User::find($user->id);

// The facade (Model::find) goes through the EM: the heap hit returns the
// SAME instance — no duplicate objects, no lost in-memory state.
echo "Same instance from find(): " . var_export($again === $user, true) . "\n\n";

// ============================================================================
// Unit of work — flush() executes everything in ONE transaction
// ============================================================================

echo "=== Unit of work: many changes, one flush ===\n\n";

$follower1 = new User();
$follower1->username = 'bob';
$follower1->email    = 'bob@example.com';

$follower2 = new User();
$follower2->username = 'carol';
$follower2->email    = 'carol@example.com';

$em->persist($follower1);
$em->persist($follower2);
$em->persist($user); // email change again — all three run in one tx

$em->flush();
echo "Flushed 3 writes in one transaction: {$follower1->id}, {$follower2->id}, {$user->id}\n";
echo "bob's posts_count stayed untouched by alice's diff-update: "
    . var_export($follower1->posts_count === 0, true) . "\n\n";

// ============================================================================
// Referential ordering — owners flush before dependents
// ============================================================================

echo "=== Topological ordering (owner before dependent) ===\n\n";

// The EM knows Post belongsTo User (from the attributes), so scheduling
// them in any order still inserts the OWNER first — the owner's
// auto-generated PK exists by the time the dependent's statement runs.
$author = new User();
$author->username = 'dave';
$author->email    = 'dave@example.com';

$em->persist($author);
$em->flush(); // author gets its id first (or batch it — see BulkWritesExample)

$post = new Post();
$post->title   = 'Hello from the EM';
$post->user_id = $author->id; // FK copy AFTER the owner's id is backfilled

$em->persist($post);
$em->flush();

echo "Author inserted as id {$author->id}; post {$post->id} references user {$post->user_id}.\n\n";

// ============================================================================
// upsert() — atomic INSERT ... ON CONFLICT DO UPDATE
// ============================================================================

echo "=== upsert() ===\n\n";

// A full identity (all PK fields set) makes the PK the conflict target:
$user2 = new User();
$user2->id       = $follower1->id;
$user2->username = 'bob-renamed';
$user2->email    = 'bob@example.com';
$user2->status   = 'active';

$em->upsert($user2)->flush(); // single atomic statement
echo "Upserted: " . User::find($follower1->id)->username . "\n";

// Without a full identity there is no conflict target — upsert() degrades
// to a plain INSERT (scheduleInsert), same as persist().
$newcomer = new User();
$newcomer->username = 'erin';
$newcomer->email    = 'erin@example.com';
$em->upsert($newcomer)->flush();
echo "PK-less upsert behaved as insert: id={$newcomer->id}\n\n";

// ============================================================================
// remove() — queue a DELETE (flush() executes it)
// ============================================================================

echo "=== remove() ===\n\n";

$em->remove($follower2);
echo "Scheduled: " . var_export($em->isScheduled($follower2), true) . "\n";
$em->flush();
echo "Removed carol (id {$follower2->id}). Exists: "
    . var_export(User::exists(['username' => 'carol']), true) . "\n\n";

// ============================================================================
// adopt() / track() — bridging externally-loaded entities into the EM
// ============================================================================

echo "=== adopt() vs track() ===\n\n";

// Entities loaded OUTSIDE the EM pipeline (FETCH_CLASS ResultSet, Paginator,
// manually built instances) are not tracked. adopt() registers them with an
// EMPTY baseline — the next flush writes EVERY set column (blind-UPDATE
// parity for manually built ID'd entities).
$handBuilt = new User();
$handBuilt->id       = $user->id;
$handBuilt->username = 'alice';
$handBuilt->email    = 'adopted@example.com';
$handBuilt->status   = 'active';

$em->adopt($handBuilt);
$em->persist($handBuilt)->flush(); // writes all set non-PK columns
echo "adopt() wrote every set column: " . User::find($user->id)->email . "\n";

// track() registers with the CURRENT values as the baseline — afterwards
// persist() writes only fields changed after the track() call.
$onlyChange = User::find($user->id);
$em->track($onlyChange);
$onlyChange->status = 'inactive';
$em->persist($onlyChange)->flush(); // UPDATE ... SET status = ? only
echo "track() diff-updated one column: status = "
    . User::find($user->id)->status . "\n\n";

// ============================================================================
// Dirty state: isDirty(), dirtyData(), revert()
// ============================================================================

echo "=== Dirty state API ===\n\n";

$dirty = User::find($user->id);
echo "Clean after load: " . var_export($em->isDirty($dirty), true) . "\n";

$dirty->email       = 'changed@example.com';
$dirty->posts_count = 7;
echo "After edits: " . var_export($em->isDirty($dirty), true) . "\n";
echo "Changed fields: " . json_encode($em->dirtyData($dirty)) . "\n";

// Discard the in-memory changes (restores the snapshot values):
$em->revert($dirty);
echo "After revert: " . json_encode($em->dirtyData($dirty)) . "\n";
echo "Email back to: " . User::find($user->id)->email . "\n\n";

// ============================================================================
// detach() — drop from identity tracking (no storage effect)
// ============================================================================

echo "=== detach() ===\n\n";

$em->detach($dirty);
echo "Contains after detach: " . var_export($em->contains($dirty), true) . "\n";
echo "A subsequent find() hydrates a fresh instance: "
    . var_export(User::find($user->id) === $dirty, true) . "\n\n";

// ============================================================================
// Reads through the EM: find() / findBy() with $fresh
// ============================================================================

echo "=== Fresh reads ===\n\n";

$tracked = User::find($user->id);

// Default: heap hit returns the tracked instance WITHOUT touching the store
// (identity-map semantics — may be stale in long-lived workers).
$stale = User::find($user->id);

// $fresh=true re-reads the row and refreshes the SAME instance in place:
$fresh = User::find($user->id, fresh: true);
echo "fresh=true returns the same instance, re-hydrated: "
    . var_export($fresh === $tracked, true) . "\n";
echo "Stale-read escape hatch also exists on criteria reads: "
    . "User::fresh()->findAll([...])\n\n";

// ============================================================================
// Transaction participation — flush() joins an open transaction
// ============================================================================

echo "=== flush() joins the caller's transaction ===\n\n";

$db = AppContext::instance()->dbManager()->getDefault();
$db->begin();

$txUser = new User();
$txUser->username = 'frank';
$txUser->email    = 'frank@example.com';
$em->persist($txUser);

// Deliberate failure AFTER the first write — flush() would commit both,
// but the outer rollback discards everything:
try {
    $em->flush();
    echo "Flush joined the open transaction (no nested commit).\n";
    $db->rollback();
    echo "Rolled back — frank exists: "
        . var_export(User::exists(['username' => 'frank']), true) . "\n";
} catch (\Throwable $e) {
    $db->rollback();
    throw $e;
}

echo "\nExample completed.\n";