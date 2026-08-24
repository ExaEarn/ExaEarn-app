<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinanceAdjustmentRequest extends Model { protected $guarded = []; protected $casts = ['metadata'=>'array','amount'=>'decimal:18','approved_at'=>'datetime']; }
