<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantWebhookEvent extends Model
{
    protected $fillable = [
        'event_id',
        'merchant_id',
        'event_type',
        'resource_type',
        'resource_id',
        'payload',
        'status',
    ];

    protected $casts = ['payload' => 'array'];
}
