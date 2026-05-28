<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingAuditLog extends Model
{
    use HasFactory;

    protected $table = 'trading_audit_logs';

    protected $fillable = [
        'user_id',
        'action_type',
        'symbol',
        'details',
        'ai_suggestion',
        'user_action',
        'result',
        'risk_level',
        'warnings_issued',
    ];

    protected $casts = [
        'details' => 'array',
        'ai_suggestion' => 'array',
        'user_action' => 'array',
        'result' => 'array',
        'warnings_issued' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
