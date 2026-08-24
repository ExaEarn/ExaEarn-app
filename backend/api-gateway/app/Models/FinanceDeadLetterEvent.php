<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinanceDeadLetterEvent extends Model { protected $guarded = []; protected $casts = ['payload'=>'array','next_retry_at'=>'datetime','resolved_at'=>'datetime']; }
