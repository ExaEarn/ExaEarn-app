<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidityLog extends Model
{
    protected $fillable = [
        'provider',
        'action',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }
}