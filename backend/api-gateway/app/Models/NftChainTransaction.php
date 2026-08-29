<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NftChainTransaction extends Model
{
    protected $fillable = ['nft_id', 'operation', 'chain', 'tx_hash', 'status', 'confirmations', 'payload', 'receipt'];
    protected $casts = ['payload' => 'array', 'receipt' => 'array'];
}
