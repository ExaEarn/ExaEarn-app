<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinanceBackingSnapshot extends Model { protected $guarded = []; protected $casts = ['metadata'=>'array','liability'=>'decimal:18','gross_assets'=>'decimal:18','restricted_assets'=>'decimal:18','eligible_backing'=>'decimal:18','surplus_deficit'=>'decimal:18','coverage_ratio'=>'decimal:18','calculated_at'=>'datetime']; }
