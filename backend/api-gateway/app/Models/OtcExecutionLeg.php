<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OtcExecutionLeg extends Model { protected $guarded = []; protected $casts = ['price'=>'decimal:18','base_amount'=>'decimal:18','quote_amount'=>'decimal:18','metadata'=>'array']; }
