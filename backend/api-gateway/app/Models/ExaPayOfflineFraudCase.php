<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ExaPayOfflineFraudCase extends Model
{
    protected $table = 'exapay_offline_fraud_cases';
    protected $fillable = ['case_uuid', 'user_id', 'device_id', 'reason_code', 'severity', 'status', 'evidence', 'detected_at', 'investigated_by_admin_id', 'investigated_at', 'resolution_code'];
    protected $casts = ['evidence' => 'array', 'detected_at' => 'datetime', 'investigated_at' => 'datetime'];
}
