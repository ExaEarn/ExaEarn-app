<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ComplianceJurisdiction extends Model { protected $guarded = []; protected $casts = ['effective_from'=>'datetime','effective_to'=>'datetime','metadata'=>'array']; }
