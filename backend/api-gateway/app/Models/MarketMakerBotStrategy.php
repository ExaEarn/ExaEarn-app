<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketMakerBotStrategy extends Model { protected $guarded = []; protected $casts = ['supported_markets'=>'array','parameters'=>'array','approved_at'=>'datetime']; }
