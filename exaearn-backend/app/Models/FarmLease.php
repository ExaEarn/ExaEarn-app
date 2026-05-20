<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmLease extends Model
{
    protected $fillable = [
        'project_id',
        'farmer_id',
        'investment_id',
        'assigned_by',
        'lease_terms',
        'profit_share',
        'starts_on',
        'ends_on',
        'status',
        'contract_reference',
        'metadata',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
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

    public function investment(): BelongsTo
    {
        return $this->belongsTo(FarmInvestment::class, 'investment_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
