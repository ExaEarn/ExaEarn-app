<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarginAccount extends Model
{
    public const MODE_CROSS = 'CROSS';
    public const MODE_ISOLATED = 'ISOLATED';

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_RESTRICTED = 'RESTRICTED';
    public const STATUS_LIQUIDATION_PENDING = 'LIQUIDATION_PENDING';
    public const STATUS_LIQUIDATING = 'LIQUIDATING';
    public const STATUS_FROZEN = 'FROZEN';
    public const STATUS_CLOSED = 'CLOSED';

    protected $fillable = [
        'account_uuid',
        'user_id',
        'mode',
        'market_symbol',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function loans(): HasMany
    {
        return $this->hasMany(MarginLoan::class);
    }
}
