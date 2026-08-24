<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinanceOpeningBalanceImport extends Model { protected $guarded = []; protected $casts = ['evidence'=>'array','amount'=>'decimal:18','approved_at'=>'datetime']; }
