<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OtcLiquidityProvider extends Model { protected $guarded = []; protected $casts = ['capabilities'=>'array','markets'=>'array','limits'=>'array','metadata'=>'array']; }
