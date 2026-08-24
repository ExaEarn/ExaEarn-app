<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeveloperRealtimeEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'user_id',
        'project_id',
        'environment',
        'stream',
        'sequence',
        'event_type',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(DeveloperProject::class, 'project_id');
    }
}
