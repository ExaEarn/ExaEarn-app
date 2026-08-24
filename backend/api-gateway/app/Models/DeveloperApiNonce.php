<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeveloperApiNonce extends Model
{
    protected $fillable = ['api_key_id', 'nonce', 'seen_at'];

    protected $casts = ['seen_at' => 'datetime'];
}
