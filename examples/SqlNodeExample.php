<?php
namespace Azera\SqlNodeExample;

/**
 * Sql Usage Examples
 * 
 * This file demonstrates how to use the Sql system with query builders.
 * The Sql system extends the query builder to support complex SQL expressions
 * while maintaining backward compatibility with existing code.
 */

use Azera\Db\Sql;
use Azera\Db\Query;

// ============================================================================
// BASIC USAGE: Existing behavior is preserved
// ============================================================================

// Traditional way (still works exactly as before)
Query::new()
    ->table('posts')
    ->values(['title' => 'Hello', 'status' => 1])
    ->insert();
// SQL: INSERT INTO posts (title, status) VALUES ('Hello', 1)

// With bind parameters (still works as before)
Query::new()
    ->table('posts')
    ->bind(['title' => 'Hello', 'id' => 123])
    ->insert();
// SQL: INSERT INTO posts (title, id) VALUES (:title:, :id:)


// ============================================================================
// Sql: Functions with literals (debug-friendly, no parameters)
// ============================================================================

Query::new()
    ->table('articles')
    ->values([
        'title' => 'My Article',
        'search' => Sql::func('to_tsvector', ['simple', 'My Article'])
    ])
    ->insert();
// SQL: INSERT INTO articles (title, search) 
//      VALUES ('My Article', to_tsvector('simple', 'My Article'))


// ============================================================================
// Sql: Column references in functions
// ============================================================================

Query::new()
    ->table('articles')
    ->set('search', Sql::cast(
        Sql::func('to_tsvector', ['simple', Sql::column('title')]),
        'tsvector'
    ))
    ->where('id', 1)
    ->update();
// PostgreSQL: UPDATE articles SET search = to_tsvector('simple', title)::tsvector WHERE (id = 1)
// MySQL: UPDATE articles SET search = to_tsvector('simple', title) CAST(tsvector) WHERE (id = 1)


// ============================================================================
// Sql: Explicit bind parameters with Sql::param()
// ============================================================================

Query::new()
    ->table('articles')
    ->bind(['userId' => 42, 'timestamp' => time()])
    ->set([
        'updated_by' => Sql::param('userId'),
        'updated_at' => Sql::func('FROM_UNIXTIME', [Sql::param('timestamp')])
    ])
    ->where('id', 1)
    ->update();
// SQL: UPDATE articles 
//      SET updated_by = :userId, updated_at = FROM_UNIXTIME(:timestamp)
//      WHERE (id = 1)


// ============================================================================
// Sql: PostgreSQL arrays
// ============================================================================

Query::new()
    ->table('posts')
    ->values([
        'title' => 'PHP Tutorial',
        'tags' => Sql::pgArray(['php', 'programming', 'web'])
    ])
    ->insert();
// SQL: INSERT INTO posts (title, tags)
//      VALUES ('PHP Tutorial', '{"php","programming","web"}')


// ============================================================================
// Sql: Complex expressions
// ============================================================================

Query::new()
    ->table('users')
    ->set([
        'full_name' => Sql::func('CONCAT', [
            Sql::column('first_name'),
            ' ',
            Sql::column('last_name')
        ]),
        'age' => Sql::func('TIMESTAMPDIFF', [
            Sql::raw('YEAR'),
            Sql::column('birthdate'),
            Sql::func('CURDATE')
        ])
    ])
    ->where('id', 123)
    ->update();
// SQL: UPDATE users 
//      SET full_name = CONCAT(first_name, ' ', last_name),
//          age = TIMESTAMPDIFF(YEAR, birthdate, CURDATE())
//      WHERE (id = 123)


// ============================================================================
// Sql: UPSERT with functions
// ============================================================================

Query::new()
    ->table('posts')
    ->values([
        'slug' => 'my-article',
        'title' => 'My Article',
        'view_count' => 0,
        'updated_at' => Sql::func('NOW', [])
    ])
    ->conflict(['slug'])  // PostgreSQL conflict target
    ->updateValues([
        'title' => 'My Article',
        'view_count' => Sql::func('view_count', []) . ' + 1',  // Or use raw
        'updated_at' => Sql::func('NOW', [])
    ])
    ->upsert();
// PostgreSQL: INSERT INTO posts (slug, title, view_count, updated_at)
//             VALUES ('my-article', 'My Article', 0, NOW())
//             ON CONFLICT (slug) DO UPDATE SET 
//                 title = 'My Article',
//                 view_count = view_count + 1,
//                 updated_at = NOW()


// ============================================================================
// Sql: Raw SQL for edge cases
// ============================================================================

Query::new()
    ->table('logs')
    ->values([
        'message' => 'User logged in',
        'timestamp' => Sql::raw('CURRENT_TIMESTAMP'),
        'data' => Sql::json(['key' => 'value'])
    ])
    ->insert();


// ============================================================================
// Sql: WHERE clause with Sql
// ============================================================================

Query::new()
    ->table('articles')
    ->where('tags && ?', Sql::pgArray(['php']))  // PostgreSQL array overlap
    ->select();
// SQL: SELECT * FROM articles WHERE (tags && '{"php"}')


// ============================================================================
// Sql: Mixing literals and parameters
// ============================================================================

Query::new()
    ->table('events')
    ->bind(['eventName' => 'user_login', 'userId' => 42])
    ->values([
        'event_type' => Sql::param('eventName'),
        'user_id' => Sql::param('userId'),
        'created_at' => Sql::func('NOW', []),
        'metadata' => Sql::func('JSON_OBJECT', [
            'ip',
            '192.168.1.1',
            'user_agent',
            'Mozilla/5.0'
        ])
    ])
    ->insert();
// SQL: INSERT INTO events (event_type, user_id, created_at, metadata)
//      VALUES (:eventName:, :userId:, NOW(), 
//              JSON_OBJECT('ip', '192.168.1.1', 'user_agent', 'Mozilla/5.0'))
