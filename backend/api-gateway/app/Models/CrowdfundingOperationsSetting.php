<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrowdfundingOperationsSetting extends Model
{
    public const UPDATED_AT = 'updated_at';
    public const CREATED_AT = 'created_at';

    protected $guarded = [];
    protected $casts = ['value' => 'array'];
}
