<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeveloperProductionAccessReview extends Model
{
    protected $fillable=['event_uuid','request_id','actor_user_id','actor_admin_id','action','from_status','to_status','public_message','internal_note','context','idempotency_key'];
    protected $casts=['context'=>'array'];
    protected $hidden=['internal_note'];
}
