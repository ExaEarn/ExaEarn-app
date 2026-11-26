<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingDepositReadinessCheck extends Model
{
    protected $guarded = [];

    protected $casts = ['gates' => 'array', 'evidence' => 'array', 'checked_at' => 'datetime'];
}
