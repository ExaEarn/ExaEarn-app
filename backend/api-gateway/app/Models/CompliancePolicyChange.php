<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CompliancePolicyChange extends Model { protected $guarded = []; protected $casts = ['previous_value'=>'array','new_value'=>'array','effective_at'=>'datetime','expires_at'=>'datetime','approved_at'=>'datetime']; }
