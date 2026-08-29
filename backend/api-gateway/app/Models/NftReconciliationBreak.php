<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NftReconciliationBreak extends Model
{
    protected $fillable = ['nft_id', 'break_type', 'severity', 'status', 'evidence'];
    protected $casts = ['evidence' => 'array'];
}
