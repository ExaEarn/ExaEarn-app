<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinanceReconciliationBreak extends Model { protected $guarded = []; protected $casts = ['evidence'=>'array','resolved_at'=>'datetime']; }
