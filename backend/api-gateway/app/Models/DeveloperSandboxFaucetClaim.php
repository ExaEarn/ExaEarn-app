<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeveloperSandboxFaucetClaim extends Model
{
    protected $fillable = ['user_id', 'project_id', 'asset', 'amount', 'claimed_at'];

    protected $casts = ['amount' => 'decimal:8', 'claimed_at' => 'datetime'];
}
