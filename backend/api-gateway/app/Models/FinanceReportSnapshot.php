<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinanceReportSnapshot extends Model { protected $guarded = []; protected $casts = ['payload'=>'array','period_start'=>'date','period_end'=>'date','valuation_at'=>'datetime','generated_at'=>'datetime']; }
