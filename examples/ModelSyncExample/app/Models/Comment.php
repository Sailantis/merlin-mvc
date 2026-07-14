<?php

namespace App\Models;

use Merlin\Core\Model;

/**
 * Sync by file path:   php console.php model-sync model app/Models/Comment.php --apply
 * Sync by class name:  php console.php model-sync model Comment --apply
 * With accessors:      php console.php model-sync model Comment --apply --generate-accessors --field-visibility=protected
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
