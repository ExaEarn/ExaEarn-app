<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketMakerBotHedge extends Model { protected $guarded = []; protected $casts = ['target_hedge_ratio'=>'decimal:8','target_notional'=>'decimal:18','actual_notional'=>'decimal:18','risk_snapshot'=>'array','metadata'=>'array']; }
