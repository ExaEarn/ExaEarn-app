<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinanceClosePeriod extends Model { protected $guarded = []; protected $casts = ['summary'=>'array','period_start'=>'date','period_end'=>'date','prepared_at'=>'datetime','approved_at'=>'datetime']; }
