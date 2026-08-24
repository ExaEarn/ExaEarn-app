<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketMakerBotLoadRun extends Model { protected $guarded = []; protected $casts = ['metrics'=>'array','metadata'=>'array','started_at'=>'datetime','finished_at'=>'datetime']; }
