<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopyComplaint extends Model
{
    protected $fillable = [
        'complaint_id',
        'follower_user_id',
        'lead_trader_id',
        'copy_relationship_id',
        'copy_order_id',
        'lead_trade_event_id',
        'category',
        'status',
        'message',
        'evidence',
        'reviewed_by',
        'reviewed_at',
        'resolution',
    ];

    protected $casts = [
        'evidence' => 'array',
        'reviewed_at' => 'datetime',
    ];
}
