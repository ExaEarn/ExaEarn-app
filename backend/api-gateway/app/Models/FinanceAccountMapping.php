<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinanceAccountMapping extends Model { protected $guarded = []; protected $casts = ['metadata'=>'array','effective_at'=>'datetime','expires_at'=>'datetime']; }
