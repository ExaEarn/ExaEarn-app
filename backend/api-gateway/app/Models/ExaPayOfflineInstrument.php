<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaPayOfflineInstrument extends Model
{
    protected $table = 'exapay_offline_instruments';

    protected $fillable = ['instrument_uuid', 'reference', 'user_id', 'device_id', 'asset', 'amount_minor', 'idempotency_key', 'request_hash', 'device_counter', 'protocol_version', 'nonce', 'payload', 'issuer_signature', 'issuer_public_key', 'status', 'delivery_status', 'issued_at', 'expires_at', 'acknowledged_at', 'consumed_at'];

    protected $casts = ['amount_minor' => 'integer', 'device_counter' => 'integer', 'protocol_version' => 'integer', 'payload' => 'array', 'issued_at' => 'datetime', 'expires_at' => 'datetime', 'acknowledged_at' => 'datetime', 'consumed_at' => 'datetime'];

    protected $hidden = ['issuer_signature'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(ExaPayOfflineDevice::class, 'device_id');
    }
}
