<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketMakerBotPerformanceSnapshot extends Model { protected $guarded = []; protected $casts = ['maker_volume'=>'decimal:18','realized_pnl'=>'decimal:18','unrealized_pnl'=>'decimal:18','fees'=>'decimal:18','rebates'=>'decimal:18','drawdown_bps'=>'decimal:8','cancel_ratio'=>'decimal:8','metadata'=>'array','measured_at'=>'datetime']; }
