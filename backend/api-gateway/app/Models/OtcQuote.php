<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OtcQuote extends Model { protected $guarded = []; protected $casts = ['price'=>'decimal:18','available_base_amount'=>'decimal:18','minimum_base_amount'=>'decimal:18','provider_fee'=>'decimal:18','client_price'=>'decimal:18','client_fee'=>'decimal:18','valid_until'=>'datetime','validation_snapshot'=>'array','best_execution_snapshot'=>'array','metadata'=>'array']; }
