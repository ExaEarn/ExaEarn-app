<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketMakerBotOrder extends Model { protected $guarded = []; protected $casts = ['price'=>'decimal:18','quantity'=>'decimal:18','metadata'=>'array']; }
