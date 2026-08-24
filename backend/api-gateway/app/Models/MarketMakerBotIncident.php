<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketMakerBotIncident extends Model { protected $guarded = []; protected $casts = ['evidence'=>'array','opened_at'=>'datetime','resolved_at'=>'datetime']; }
