<?php

namespace App\Models;

use Azera\Core\Model;

/**
 * Sync by file path:   php console.php model-sync model app/Models/User.php --apply
 * Sync by class name:  php console.php model-sync model User --apply
 * With accessors:      php console.php model-sync model User --apply --generate-accessors --field-visibility=protected
 */
class User extends Model
{
    public int $id;
    public string $email;
    public ?string $name;
    public string $status;
    public string $created_at;
    public ?string $updated_at;
    public ?string $avatar_url;

    // Properties will be added automatically by the sync task.
}
