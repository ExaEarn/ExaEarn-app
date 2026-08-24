<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VipTierHistory extends Model
{
    protected $table = 'vip_tier_history';

    protected $fillable = ['institution_id', 'previous_tier', 'automatic_tier', 'manual_override_tier', 'contractual_tier', 'effective_tier', 'reason', 'changed_by_admin_id', 'inputs'];

    protected $casts = ['inputs' => 'array'];
}
