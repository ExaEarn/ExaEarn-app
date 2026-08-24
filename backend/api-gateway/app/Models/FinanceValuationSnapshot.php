<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinanceValuationSnapshot extends Model { protected $guarded = []; protected $casts = ['metadata'=>'array','rate'=>'decimal:18','valued_at'=>'datetime']; }
