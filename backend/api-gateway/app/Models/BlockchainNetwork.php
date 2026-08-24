<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlockchainNetwork extends Model
{
    protected $fillable = ['network', 'family', 'chain_id', 'native_asset', 'state', 'deposit_enabled', 'withdrawal_enabled', 'required_confirmations', 'finality_confirmations', 'memo_required', 'policy', 'last_health_checked_at'];

    protected $casts = ['deposit_enabled' => 'boolean', 'withdrawal_enabled' => 'boolean', 'memo_required' => 'boolean', 'policy' => 'array', 'last_health_checked_at' => 'datetime'];

    public function assets(): HasMany
    {
        return $this->hasMany(BlockchainAsset::class);
    }
}
