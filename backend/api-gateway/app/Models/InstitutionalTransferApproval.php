<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionalTransferApproval extends Model
{
    protected $fillable = ['transfer_id', 'approver_user_id', 'decision', 'reason', 'decided_at'];

    protected $casts = ['decided_at' => 'datetime'];
}
