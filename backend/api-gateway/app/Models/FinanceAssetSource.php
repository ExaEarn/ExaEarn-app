<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinanceAssetSource extends Model { protected $guarded = []; protected $casts = ['metadata'=>'array','amount'=>'decimal:18','eligible_for_backing'=>'boolean','restricted'=>'boolean','verified_at'=>'datetime']; }
