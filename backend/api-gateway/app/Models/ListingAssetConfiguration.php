<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingAssetConfiguration extends Model
{
    protected $fillable = ['asset_config_uuid', 'application_id', 'blockchain_asset_id', 'asset_uid', 'name', 'symbol', 'slug', 'asset_type', 'network', 'token_standard', 'contract_address', 'decimals', 'explorer_url', 'logo_path', 'status', 'deposit_enabled', 'withdrawal_enabled', 'trading_enabled', 'supply_metadata', 'configuration_history'];

    protected $casts = ['deposit_enabled' => 'boolean', 'withdrawal_enabled' => 'boolean', 'trading_enabled' => 'boolean', 'supply_metadata' => 'array', 'configuration_history' => 'array'];
}
