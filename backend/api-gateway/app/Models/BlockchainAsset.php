<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockchainAsset extends Model
{
    protected $fillable = ['blockchain_network_id', 'asset', 'network', 'asset_type', 'contract_address', 'decimals', 'deposit_enabled', 'withdrawal_enabled', 'minimum_deposit', 'minimum_withdrawal', 'maximum_withdrawal', 'required_confirmations', 'sweep_threshold', 'rebalance_threshold', 'fee_policy', 'metadata'];

    protected $casts = ['deposit_enabled' => 'boolean', 'withdrawal_enabled' => 'boolean', 'fee_policy' => 'array', 'metadata' => 'array'];

    public function networkModel(): BelongsTo
    {
        return $this->belongsTo(BlockchainNetwork::class, 'blockchain_network_id');
    }
}
