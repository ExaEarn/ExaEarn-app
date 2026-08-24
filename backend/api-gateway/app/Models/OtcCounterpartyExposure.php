<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OtcCounterpartyExposure extends Model { protected $guarded = []; protected $casts = ['credit_limit'=>'decimal:18','settlement_limit'=>'decimal:18','outstanding_receivable'=>'decimal:18','outstanding_payable'=>'decimal:18','unsettled_notional'=>'decimal:18','metadata'=>'array']; }
