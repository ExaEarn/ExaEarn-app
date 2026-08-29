<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillsInstructorPayout extends Model
{
    protected $fillable = ['instructor_user_id', 'asset', 'amount', 'status', 'destination_type', 'reference', 'idempotency_key', 'reviewed_by_admin_id', 'requested_at', 'reviewed_at', 'completed_at', 'earning_ids', 'metadata'];

    protected $casts = ['amount' => 'decimal:8', 'requested_at' => 'datetime', 'reviewed_at' => 'datetime', 'completed_at' => 'datetime', 'earning_ids' => 'array', 'metadata' => 'array'];
}
