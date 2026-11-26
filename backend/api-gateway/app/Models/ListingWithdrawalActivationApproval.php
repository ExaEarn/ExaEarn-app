<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingWithdrawalActivationApproval extends Model
{
    protected $guarded = [];
    protected $casts = ['readiness_snapshot' => 'array', 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'consumed_at' => 'datetime'];
}
