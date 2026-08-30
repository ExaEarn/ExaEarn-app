<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeveloperProductionCapabilityReview extends Model { protected $fillable=['review_uuid','request_id','capability_id','admin_id','canonical_user_id','decision','review_sequence','reason_code','internal_note','policy_version','idempotency_key']; protected $hidden=['internal_note']; }
