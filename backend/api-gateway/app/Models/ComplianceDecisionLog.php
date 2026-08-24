<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ComplianceDecisionLog extends Model { protected $guarded = []; protected $casts = ['effective_limits'=>'array','metadata'=>'array','decided_at'=>'datetime']; }
