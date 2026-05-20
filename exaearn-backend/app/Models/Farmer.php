<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Farmer extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'location',
        'experience_years',
        'verification_status',
        'identity_documents',
        'equipment_details',
        'geo_metadata',
        'bio',
    ];

    protected $casts = [
        'identity_documents' => 'array',
        'equipment_details' => 'array',
        'geo_metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(FarmLease::class);
    }

    public function produceUpdates(): HasMany
    {
        return $this->hasMany(ProduceTracking::class);
    }
}
