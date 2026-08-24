<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Phase15EmergencyControl extends Model { protected $guarded = []; protected $casts = ['previous_state'=>'array','new_state'=>'array','activated_at'=>'datetime','resolved_at'=>'datetime']; }
