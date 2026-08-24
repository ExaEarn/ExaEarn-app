<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionalFeeProfile extends Model
{
    protected $fillable = ['fee_profile_uuid', 'name', 'status', 'rules', 'created_by_admin_id', 'reason', 'effective_at'];

    protected $casts = ['rules' => 'array', 'effective_at' => 'datetime'];
}
