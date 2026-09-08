<?php

namespace App\Models;

use Azera\Orm\Model;

/**
 * Sync by file path:   php console.php model sync app/Models/Post.php --apply
 * Sync by class name:  php console.php model sync Post --apply
 * With accessors:      php console.php model sync Post --apply --generate-accessors --field-visibility=protected
 */
class Post extends Model
{
    public int $id;
    public int $user_id;
    public string $title;
    public ?string $content;
    public int $published;
    public string $created_at;

    // Properties will be added automatically by the sync task.
}
