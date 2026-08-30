<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class DeveloperProductionCapability extends Model
{
    protected $fillable=['request_id','project_id','capability','status','reason_code','limits','decided_by','decided_at','required_approvals','approval_count'];
    protected $casts=['limits'=>'array','decided_at'=>'datetime'];
    public function reviews(): HasMany{return $this->hasMany(DeveloperProductionCapabilityReview::class,'capability_id');}
}
