<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OtcReconciliationRun extends Model { protected $guarded = []; protected $casts = ['summary'=>'array','started_at'=>'datetime','finished_at'=>'datetime']; }
