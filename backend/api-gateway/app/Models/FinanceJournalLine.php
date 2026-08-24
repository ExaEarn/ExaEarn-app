<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinanceJournalLine extends Model { protected $guarded = []; protected $casts = ['metadata'=>'array','debit'=>'decimal:18','credit'=>'decimal:18','reporting_value'=>'decimal:18','valuation_rate'=>'decimal:18','valuation_at'=>'datetime']; }
