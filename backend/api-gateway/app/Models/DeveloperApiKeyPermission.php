<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeveloperApiKeyPermission extends Model
{
    protected $fillable = ['api_key_id', 'permission'];
}
