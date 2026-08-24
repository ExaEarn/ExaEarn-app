<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialOperationIdempotency extends Model
{
    protected $fillable = [
        'idempotency_key',
        'operation_type',
        'reference',
        'status',
        'request_hash',
        'response_snapshot',
    ];

    protected $casts = [
        'request_hash' => 'array',
        'response_snapshot' => 'array',
    ];
}
