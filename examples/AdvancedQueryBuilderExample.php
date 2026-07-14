<?php
namespace Merlin\AdvancedQueryBuilderExample;

require_once __DIR__ . '/../vendor/autoload.php';

use Merlin\AppContext;
use Merlin\Db\Database;
use Merlin\Db\Sql;
use Merlin\Core\Model;

/**
 * Example: Advanced Query Building with Sql and Complex Queries
 * 
 * This example demonstrates sophisticated database operations:
 * - Sql for raw SQL expressions
 * - Complex WHERE conditions with subqueries
 * - Multi-table joins with aggregations
 * - Window functions and grouping
 * - Transaction-aware operations
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
    public int $reputation = 0;
    public string $status = 'active';
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
    public int $like_count = 0;
    public string $status = 'draft';
    public string $created_at;
}

class Comment extends Model
{
    public int $id;
    public int $post_id;
    public int $user_id;
    public string $content;
    public int $like_count = 0;
    public string $created_at;
}

class Tag extends Model
{
    public int $id;
    public string $name;
    public int $usage_count = 0;
}

class PostTag extends Model
{
    public int $post_id;
    public int $tag_id;

    public function idFields(): array
    {
        return ['post_id', 'tag_id'];
    }
}

// ============================================================================
// Sql - Raw SQL Expressions
// ============================================================================

echo "=== Sql - Raw SQL Expressions ===\n\n";

// 1. Increment counter with Sql
echo "1. Increment view counter:\n";
Post::query()
    ->set('view_count', Sql::raw('view_count + 1'))
    ->where('id = :id', ['id' => 5])
    ->update();
echo "   Post view_count incremented.\n";

echo "\n";

// 2. Update with conditional expression
echo "2. Update status based on conditions:\n";
Post::query()
    ->set('status', Sql::raw(
        'CASE WHEN view_count > :popular THEN :published ELSE :draft END',
        ['popular' => 100, 'published' => 'published', 'draft' => 'draft']
    ))
    ->where('user_id = :user_id', ['user_id' => 1])
    ->update();
echo "   Post status updated based on view_count.\n";

echo "\n";

// 3. Decrement stock with Sql
echo "3. Complex stock update:\n";
// Assuming there's an Order model
Post::query()
    ->set('like_count', Sql::raw(
        'GREATEST(like_count - :qty, 0)',  // Don't go below 0
        ['qty' => 2]
    ))
    ->where('id = :id', ['id' => 5])
    ->update();
echo "   Like count decreased (minimum 0).\n";

echo "\n";

// 4. Concatenate strings with Sql
echo "4. Update username from parts:\n";
User::query()
    ->set('username', Sql::raw(
        'CONCAT(SUBSTRING(email, 1, LOCATE("@", email) - 1), "_user")'
    ))
    ->where('id = :id', ['id' => 2])
    ->update();
echo "   Username updated from email prefix.\n";

echo "\n\n";

// ============================================================================
// COMPLEX WHERE CONDITIONS
// ============================================================================

echo "=== Complex WHERE Conditions ===\n\n";

// 1. Subquery in WHERE clause
echo "1. Find posts from popular users:\n";
$popularUserIds = User::query()
    ->columns('id')
    ->where('reputation > :threshold', ['threshold' => 100]);

$posts = Post::query()
    ->columns(['id', 'title', 'user_id'])
    ->where('user_id IN (:user_ids)', ['user_ids' => $popularUserIds])
    ->where('status = :status', ['status' => 'published'])
    ->select();

echo "   Found " . count($posts) . " posts from popular users.\n";

echo "\n";

// 2. Multiple conditions with AND/OR
echo "2. Complex condition combinations:\n";
$users = User::query()
    ->where('status = :status', ['status' => 'active'])
    ->where('(reputation >= :min_rep OR created_at > :recent_date)', [
        'min_rep' => 50,
        'recent_date' => date('Y-m-d', strtotime('-30 days'))
    ])
    ->select();

echo "   Found " . count($users) . " active/new users.\n";

echo "\n";

// 3. NOT IN subquery
echo "3. Posts without comments:\n";
$postsWithoutComments = Post::query('p')
    ->where('p.id NOT IN (:comment_posts)', [
        'comment_posts' => Comment::query()->columns('DISTINCT post_id')
    ])
    ->select();

echo "   Found " . count($postsWithoutComments) . " posts without comments.\n";

echo "\n";

// 4. BETWEEN conditions
echo "4. Posts from popularity range:\n";
$moderatelyPopularPosts = Post::query()
    ->where('view_count BETWEEN :min AND :max', [
        'min' => 10,
        'max' => 1000
    ])
    ->select();

echo "   Found " . count($moderatelyPopularPosts) . " moderately popular posts.\n";

echo "\n\n";

// ============================================================================
// JOINS WITH AGGREGATIONS
// ============================================================================

echo "=== Joins with Aggregations ===\n\n";

// 1. Join with aggregation
echo "1. Users with post counts:\n";
$userStats = User::query('u')
    ->columns([
        'u.id',
        'u.username',
        'COUNT(p.id) as post_count',
        'SUM(p.view_count) as total_views',
        'AVG(p.like_count) as avg_likes'
    ])
    ->leftJoin(Post::class, 'p', 'p.user_id = u.id')
    ->where('p.status = :status', ['status' => 'published'])
    ->where('u.status = :user_status', ['user_status' => 'active'])
    ->groupBy('u.id')
    ->orderBy('post_count DESC')
    ->select();

foreach ($userStats as $user) {
    echo "   User: {$user->username} - Posts: {$user->post_count}, Views: {$user->total_views}\n";
}

echo "\n";

// 2. Multiple joins with filtering
echo "2. Popular posts with comment counts:\n";
$popPosts = Post::query('p')
    ->columns([
        'p.id',
        'p.title',
        'u.username',
        'COUNT(c.id) as comment_count',
        'MAX(c.created_at) as last_comment'
    ])
    ->join(User::class, 'u', 'u.id = p.user_id')
    ->leftJoin(Comment::class, 'c', 'c.post_id = p.id')
    ->where('p.view_count > :views', ['views' => 100])
    ->where('p.status = :status', ['status' => 'published'])
    ->groupBy('p.id')
    ->having('COUNT(c.id) > :min_comments', ['min_comments' => 0])
    ->orderBy('comment_count DESC')
    ->select();

foreach ($popPosts as $post) {
    echo "   \"{$post->title}\" by {$post->username} - {$post->comment_count} comments\n";
}

echo "\n\n";

// ============================================================================
// ADVANCED GROUPING
// ============================================================================

echo "=== Advanced Grouping ===\n\n";

// 1. Group with multiple aggregations
echo "1. Detailed post statistics by user:\n";
$postStats = Post::query('p')
    ->columns([
        'p.user_id',
        'COUNT(*) as total_posts',
        'SUM(p.view_count) as total_views',
        'SUM(p.like_count) as total_likes',
        'AVG(p.view_count) as avg_views',
        'MAX(p.view_count) as max_views',
        'MIN(p.view_count) as min_views'
    ])
    ->where('p.status = :status', ['status' => 'published'])
    ->groupBy('p.user_id')
    ->having('COUNT(*) >= :min_posts', ['min_posts' => 5])
    ->orderBy('total_views DESC')
    ->select();

foreach ($postStats as $stat) {
    echo "   User {$stat->user_id}: {$stat->total_posts} posts, {$stat->total_views} views\n";
}

echo "\n";

// 2. Group by multiple columns
echo "2. Statistics grouped by user and status:\n";
$statusStats = Post::query()
    ->columns([
        'user_id',
        'status',
        'COUNT(*) as count',
        'SUM(view_count) as views'
    ])
    ->groupBy('user_id, status')
    ->select();

foreach ($statusStats as $stat) {
    echo "   User {$stat->user_id} ({$stat->status}): {$stat->count} posts\n";
}

echo "\n\n";

// ============================================================================
// RANKING AND WINDOW FUNCTIONS (Database-specific)
// ============================================================================

echo "=== Ranking and Window Functions ===\n\n";

// 1. Rank posts by views (MySQL 8.0+)
echo "1. Top posts ranked by views:\n";
$rankedPosts = Post::query()
    ->columns([
        'id',
        'title',
        'view_count',
        Sql::raw('ROW_NUMBER() OVER (ORDER BY view_count DESC) as rank')
    ])
    ->where('status = :status', ['status' => 'published'])
    ->select();

foreach ($rankedPosts as $post) {
    echo "   #{$post->rank}: {$post->title} ({$post->view_count} views)\n";
}

echo "\n";

// 2. Running totals (complex calculation)
echo "2. Running total of likes:\n";
$runningTotals = Post::query()
    ->columns([
        'id',
        'title',
        'like_count',
        Sql::raw('SUM(like_count) OVER (ORDER BY id) as cumulative_likes')
    ])
    ->orderBy('id ASC')
    ->select();

foreach ($runningTotals as $post) {
    echo "   Post {$post->id}: {$post->like_count} likes (total: {$post->cumulative_likes})\n";
}

echo "\n\n";

// ============================================================================
// DISTINCT AND UNIONS
// ============================================================================

echo "=== Distinct Values ===\n\n";

// 1. Find distinct authors
echo "1. All users who have published posts:\n";
$authors = Post::query('p')
    ->columns('DISTINCT p.user_id')
    ->where('p.status = :status', ['status' => 'published'])
    ->select();

echo "   Found " . count($authors) . " authors with published posts.\n";

echo "\n";

// 2. Find all unique tags
echo "2. Popular tags (used in multiple posts):\n";
$popularTags = Tag::query('t')
    ->columns([
        't.id',
        't.name',
        't.usage_count'
    ])
    ->where('t.usage_count > :min_usage', ['min_usage' => 5])
    ->orderBy('usage_count DESC')
    ->select();

echo "   Found " . count($popularTags) . " popular tags.\n";

echo "\n\n";

// ============================================================================
// PAGINATION WITH COMPLEX QUERIES
// ============================================================================

echo "=== Pagination with Complex Queries ===\n\n";

$page = 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

echo "1. Paginated user activity feed:\n";
$feedItems = Post::query('p')
    ->columns([
        'p.id',
        'p.title',
        'u.username',
        'p.created_at',
        'COUNT(c.id) as comment_count'
    ])
    ->join(User::class, 'u', 'u.id = p.user_id')
    ->leftJoin(Comment::class, 'c', 'c.post_id = p.id')
    ->where('p.status = :status', ['status' => 'published'])
    ->groupBy('p.id')
    ->orderBy('p.created_at DESC')
    ->limit($perPage)
    ->offset($offset)
    ->select();

echo "   Page {$page}: Showing " . count($feedItems) . " items\n";

echo "\n\n";

// ============================================================================
// TRANSACTIONS WITH COMPLEX OPERATIONS
// ============================================================================

echo "=== Transactions with Complex Operations ===\n\n";

echo "1. Atomic update multiple related tables:\n";

try {
    $db->begin();

    // Create a new post
    $newPost = new Post();
    $newPost->user_id = 1;
    $newPost->title = 'Complex Transaction Example';
    $newPost->content = 'This post demonstrates atomic operations.';
    $newPost->status = 'published';
    $newPost->created_at = date('Y-m-d H:i:s');
    $newPost->save();

    // Add tags to the post
    $tagNames = ['php', 'database', 'examples'];
    foreach ($tagNames as $tagName) {
        // Find or create tag
        $tag = Tag::findOne(['name' => $tagName]);
        if (!$tag) {
            $tag = new Tag();
            $tag->name = $tagName;
            $tag->save();
        }

        // Link post to tag
        PostTag::query()
            ->values([
                'post_id' => $newPost->id,
                'tag_id' => $tag->id
            ])
            ->insert();

        // Increment tag usage
        Tag::query()
            ->set('usage_count', Sql::raw('usage_count + 1'))
            ->where('id = :id', ['id' => $tag->id])
            ->update();
    }

    // Update user's reputation
    User::query()
        ->set('reputation', Sql::raw('reputation + :points', ['points' => 10]))
        ->where('id = :id', ['id' => $newPost->user_id])
        ->update();

    $db->commit();
    echo "   Transaction completed successfully.\n";

} catch (\Exception $e) {
    $db->rollBack();
    echo "   Transaction failed: " . $e->getMessage() . "\n";
}

echo "\n\n";

// ============================================================================
// BATCH OPERATIONS WITH Sql
// ============================================================================

echo "=== Batch Operations with Sql ===\n\n";

echo "1. Bulk increment with conditions:\n";
Post::query()
    ->set('like_count', Sql::raw('like_count + :increment', ['increment' => 5]))
    ->where('view_count > :threshold', ['threshold' => 1000])
    ->where('status = :status', ['status' => 'published'])
    ->update();
echo "   Highly viewed published posts incremented.\n";

echo "\n";

echo "2. Normalize data with Sql:\n";
User::query()
    ->set('username', Sql::raw('LOWER(TRIM(username))'))
    ->update();
echo "   All usernames normalized.\n";

echo "\n";

echo "3. Archive old posts:\n";
Post::query()
    ->set('status', 'archived')
    ->set('updated_at', Sql::raw('NOW()'))
    ->where('created_at < :date', ['date' => date('Y-m-d', strtotime('-1 year'))])
    ->where('status != :archived', ['archived' => 'archived'])
    ->update();
echo "   Posts older than 1 year archived.\n";

echo "\n\n";

// ============================================================================
// ANALYTICAL QUERIES
// ============================================================================

echo "=== Analytical Queries ===\n\n";

echo "1. Find outlier posts (unusually high views):\n";
$avgViews = Post::query()
    ->columns('AVG(view_count) as avg_views')
    ->select()
    ->fetchObject()->avg_views ?? 0;

$outliers = Post::query()
    ->columns(['id', 'title', 'view_count'])
    ->where('view_count > :threshold', ['threshold' => $avgViews * 2])
    ->orderBy('view_count DESC')
    ->limit(5)
    ->select();

echo "   Average views: " . number_format($avgViews) . "\n";
echo "   Found " . count($outliers) . " outlier posts.\n";

echo "\n";

echo "2. User engagement analysis:\n";
$engagement = User::query('u')
    ->columns([
        'u.id',
        'u.username',
        'COUNT(p.id) as posts',
        'COALESCE(SUM(p.view_count), 0) as total_views',
        'COALESCE(AVG(p.view_count), 0) as avg_view_per_post'
    ])
    ->leftJoin(Post::class, 'p', 'p.user_id = u.id')
    ->groupBy('u.id')
    ->having('COUNT(p.id) > :min_posts', ['min_posts' => 0])
    ->orderBy('total_views DESC')
    ->select();

foreach ($engagement as $user) {
    echo "   {$user->username}: {$user->posts} posts, {$user->total_views} total views\n";
}

echo "\n\nAdvanced query examples completed.\n";
