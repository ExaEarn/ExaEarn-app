<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CompliancePolicyException extends Model { protected $guarded = []; protected $casts = ['metadata'=>'array','effective_from'=>'datetime','effective_to'=>'datetime']; }
