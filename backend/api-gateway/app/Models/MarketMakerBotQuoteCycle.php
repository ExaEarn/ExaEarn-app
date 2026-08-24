<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketMakerBotQuoteCycle extends Model { protected $guarded = []; protected $casts = ['fair_value'=>'decimal:18','spread_bps'=>'decimal:8','market_snapshot'=>'array','inventory_snapshot'=>'array','risk_snapshot'=>'array','quote_plan'=>'array','submitted_orders'=>'array','expires_at'=>'datetime']; }
