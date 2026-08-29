<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantTeamMember extends Model
{
    protected $fillable = ['merchant_id', 'user_id', 'role', 'permissions', 'status'];

    protected $casts = ['permissions' => 'array'];
}
