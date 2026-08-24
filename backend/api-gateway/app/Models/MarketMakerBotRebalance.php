<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketMakerBotRebalance extends Model { protected $guarded = []; protected $casts = ['amount'=>'decimal:18','risk_snapshot'=>'array','metadata'=>'array']; }
