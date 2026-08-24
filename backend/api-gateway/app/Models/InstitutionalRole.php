<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionalRole extends Model
{
    protected $fillable = ['institution_id', 'name', 'role_type', 'permissions', 'system_template'];

    protected $casts = ['permissions' => 'array', 'system_template' => 'boolean'];
}
