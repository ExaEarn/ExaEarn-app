<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Phase15ReconciliationRun extends Model { protected $guarded = []; protected $casts = ['summary'=>'array','started_at'=>'datetime','finished_at'=>'datetime']; public function differences(): HasMany { return $this->hasMany(Phase15ReconciliationDifference::class, 'run_id'); } }
