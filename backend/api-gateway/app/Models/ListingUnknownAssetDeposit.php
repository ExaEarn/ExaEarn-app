<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingUnknownAssetDeposit extends Model
{
    protected $fillable = ['event_uuid', 'network', 'contract_address', 'transaction_hash', 'address', 'user_id', 'amount', 'status', 'metadata'];

    protected $casts = ['metadata' => 'array'];
}
