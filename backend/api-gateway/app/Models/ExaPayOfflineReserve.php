<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaPayOfflineReserve extends Model
{
    protected $table = 'exapay_offline_reserves';

    protected $fillable = ['asset', 'reserved_minor', 'outstanding_liability_minor', 'issued_minor', 'redeemed_minor', 'extinguished_minor', 'version'];

    protected $casts = ['reserved_minor' => 'integer', 'outstanding_liability_minor' => 'integer', 'issued_minor' => 'integer', 'redeemed_minor' => 'integer', 'extinguished_minor' => 'integer', 'version' => 'integer'];
}
