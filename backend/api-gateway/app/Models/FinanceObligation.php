<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinanceObligation extends Model { protected $guarded = []; protected $casts = ['metadata'=>'array','original_amount'=>'decimal:18','outstanding_amount'=>'decimal:18','due_date'=>'date']; }
