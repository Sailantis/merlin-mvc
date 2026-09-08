<?php

namespace App\Models;

use Azera\Orm\Model;

/**
 * Sync by file path:   php console.php model sync app/Models/Comment.php --apply
 * Sync by class name:  php console.php model sync Comment --apply
 * With accessors:      php console.php model sync Comment --apply --generate-accessors --field-visibility=protected
 */
class Comment extends Model
{
    public int $id;
    public int $post_id;
    public int $user_id;
    public string $body;
    public string $created_at;

    // Properties will be added automatically by the sync task.
}
