<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingLaunchSchedule extends Model
{
    protected $fillable = ['schedule_uuid', 'application_id', 'announcement_at', 'deposit_open_at', 'trading_open_at', 'withdrawal_open_at', 'status', 'announcement_metadata', 'created_by_admin_id', 'approved_by_admin_id', 'approved_at'];

    protected $casts = ['announcement_at' => 'datetime', 'deposit_open_at' => 'datetime', 'trading_open_at' => 'datetime', 'withdrawal_open_at' => 'datetime', 'announcement_metadata' => 'array', 'approved_at' => 'datetime'];
}
