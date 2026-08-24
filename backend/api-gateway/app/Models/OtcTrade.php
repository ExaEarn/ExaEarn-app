<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OtcTrade extends Model { protected $guarded = []; protected $casts = ['price'=>'decimal:18','base_amount'=>'decimal:18','quote_amount'=>'decimal:18','client_fee'=>'decimal:18','accounting_snapshot'=>'array','metadata'=>'array','accepted_at'=>'datetime','settled_at'=>'datetime']; }
