<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopyTermAcceptance extends Model
{
    protected $fillable = ['user_id', 'type', 'version', 'accepted_at', 'ip_hash', 'user_agent_hash', 'metadata'];

    protected $casts = [
        'accepted_at' => 'datetime',
        'metadata' => 'array',
    ];
}
