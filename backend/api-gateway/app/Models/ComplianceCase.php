<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ComplianceCase extends Model { protected $guarded = []; protected $casts = ['evidence'=>'array','review_history'=>'array','escalated_at'=>'datetime','resolved_at'=>'datetime']; }
