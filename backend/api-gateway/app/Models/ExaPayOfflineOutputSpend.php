<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ExaPayOfflineOutputSpend extends Model
{
    protected $table = 'exapay_offline_output_spends';
    protected $fillable = ['output_uuid', 'transfer_session_id', 'status', 'spent_at'];
    protected $casts = ['spent_at' => 'datetime'];
}
