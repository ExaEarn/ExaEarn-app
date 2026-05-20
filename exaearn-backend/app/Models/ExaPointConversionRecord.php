<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaPointConversionRecord extends Model
{
    protected $fillable = [
        'user_id',
        'exapoints_converted',
        'exatokens_issued',
        'conversion_rate',
        'status',
        'transaction_hash',
        'note',
    ];

    protected $casts = [
        'exapoints_converted' => 'decimal:8',
        'exatokens_issued' => 'decimal:8',
        'conversion_rate' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
