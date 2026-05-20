<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProduceTracking extends Model
{
    protected $table = 'produce_tracking';

    protected $fillable = [
        'project_id',
        'farmer_id',
        'growth_stage',
        'update_description',
        'images',
        'geo_metadata',
        'reported_yield',
        'recorded_at',
        'verification_status',
        'metadata',
    ];

    protected $casts = [
        'images' => 'array',
        'geo_metadata' => 'array',
        'reported_yield' => 'decimal:8',
        'recorded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(FarmingProject::class, 'project_id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }
}
