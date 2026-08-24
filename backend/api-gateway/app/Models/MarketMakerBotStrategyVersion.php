<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketMakerBotStrategyVersion extends Model { protected $guarded = []; protected $casts = ['parameters'=>'array','supported_markets'=>'array','approved_at'=>'datetime']; }
