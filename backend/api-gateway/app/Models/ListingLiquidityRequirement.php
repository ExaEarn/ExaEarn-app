<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingLiquidityRequirement extends Model
{
    protected $fillable = ['application_id', 'listing_market_configuration_id', 'arrangement', 'market_maker_reference', 'required_base_liquidity', 'required_quote_liquidity', 'maximum_spread_bps', 'minimum_depth', 'liquidity_status', 'metadata'];

    protected $casts = ['metadata' => 'array'];
}
