<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeveloperSandboxBalance extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'asset',
        'available',
        'reserved',
        'metadata',
    ];

    protected $casts = [
        'available' => 'decimal:8',
        'reserved' => 'decimal:8',
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(DeveloperProject::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
