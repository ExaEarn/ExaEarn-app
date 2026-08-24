<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceFundAccount extends Model
{
    protected $fillable = ['fund_id', 'product', 'asset', 'balance', 'reserved_balance', 'status', 'metadata'];

    protected $casts = ['metadata' => 'array'];
}
