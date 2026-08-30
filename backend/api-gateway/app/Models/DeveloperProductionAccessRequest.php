<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class DeveloperProductionAccessRequest extends Model
{
    protected $fillable=['request_uuid','project_id','environment_id','submitted_by','applicant_type','use_case','status','jurisdiction','request_context','idempotency_key','version','developer_message','submitted_at','decided_at'];
    protected $casts=['request_context'=>'array','submitted_at'=>'datetime','decided_at'=>'datetime'];
    public function project(): BelongsTo{return $this->belongsTo(DeveloperProject::class,'project_id');}
    public function capabilities(): HasMany{return $this->hasMany(DeveloperProductionCapability::class,'request_id');}
    public function reviews(): HasMany{return $this->hasMany(DeveloperProductionAccessReview::class,'request_id');}
    public function capabilityReviews(): HasMany{return $this->hasMany(DeveloperProductionCapabilityReview::class,'request_id');}
}
