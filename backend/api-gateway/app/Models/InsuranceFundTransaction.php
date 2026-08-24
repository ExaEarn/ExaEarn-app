<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceFundTransaction extends Model
{
    protected $fillable = ['transaction_id', 'insurance_fund_account_id', 'type', 'amount', 'reference', 'metadata'];

    protected $casts = ['metadata' => 'array'];
}
