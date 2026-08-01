<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExaAiCapitalAllocation extends Model
{
    use HasFactory;

    protected $table = 'exaai_capital_allocations';

    protected $fillable = [
        'user_id','asset','amount','available_amount','reserved_amount','status','reference','metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'available_amount' => 'decimal:8',
        'reserved_amount' => 'decimal:8',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ExaAiSession::class, 'allocation_id');
    }
}