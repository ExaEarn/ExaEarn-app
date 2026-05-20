<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FarmingProject extends Model
{
    protected $fillable = [
        'created_by',
        'project_name',
        'location',
        'crop_type',
        'farm_size',
        'farm_size_unit',
        'investment_target',
        'duration',
        'duration_unit',
        'expected_yield',
        'yield_unit',
        'expected_harvest_date',
        'status',
        'blockchain_reference',
        'verification_documents',
        'metadata',
    ];

    protected $casts = [
        'farm_size' => 'decimal:2',
        'investment_target' => 'decimal:8',
        'expected_yield' => 'decimal:8',
        'expected_harvest_date' => 'date',
        'verification_documents' => 'array',
        'metadata' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function share(): HasOne
    {
        return $this->hasOne(FarmShare::class, 'project_id');
    }

    public function investments(): HasMany
    {
        return $this->hasMany(FarmInvestment::class, 'project_id');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(FarmLease::class, 'project_id');
    }

    public function produceUpdates(): HasMany
    {
        return $this->hasMany(ProduceTracking::class, 'project_id');
    }
}
