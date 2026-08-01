<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExaAiSubscription extends Model
{
    use HasFactory;

    protected $table = 'exaai_subscriptions';

    protected $fillable = [
        'user_id','plan_id','status','billing_cycle','settlement_asset','amount','transaction_reference',
        'starts_at','ends_at','cancelled_at','renewal_at','metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'renewal_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ExaAiPlan::class, 'plan_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ExaAiSession::class, 'subscription_id');
    }
}