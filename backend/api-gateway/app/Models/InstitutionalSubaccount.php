<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionalSubaccount extends Model
{
    protected $fillable = ['subaccount_uuid', 'institution_id', 'name', 'type', 'status', 'risk_mode', 'product_flags', 'metadata', 'archived_at'];

    protected $casts = ['product_flags' => 'array', 'metadata' => 'array', 'archived_at' => 'datetime'];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(InstitutionalAccount::class, 'institution_id');
    }
}
