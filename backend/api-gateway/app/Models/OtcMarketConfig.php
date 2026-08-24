<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OtcMarketConfig extends Model { protected $guarded = []; protected $casts = ['enabled'=>'boolean','allowed_account_types'=>'array','allowed_jurisdictions'=>'array','eligible_liquidity_sources'=>'array','metadata'=>'array','minimum_size'=>'decimal:18','maximum_size'=>'decimal:18','max_spread_bps'=>'decimal:8','manual_review_threshold'=>'decimal:18']; }
