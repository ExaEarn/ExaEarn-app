<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CompliancePolicyRule extends Model { protected $guarded = []; protected $casts = ['limits'=>'array','metadata'=>'array','effective_at'=>'datetime','expires_at'=>'datetime','max_amount'=>'decimal:18']; }
