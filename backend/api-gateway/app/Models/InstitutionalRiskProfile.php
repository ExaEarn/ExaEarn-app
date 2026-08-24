<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionalRiskProfile extends Model
{
    protected $fillable = ['institution_id', 'subaccount_id', 'product', 'market', 'status', 'limits', 'updated_by_admin_id'];

    protected $casts = ['limits' => 'array'];
}
