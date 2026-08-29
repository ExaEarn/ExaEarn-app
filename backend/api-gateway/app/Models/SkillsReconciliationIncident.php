<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillsReconciliationIncident extends Model
{
    protected $fillable = ['incident_type', 'severity', 'status', 'course_id', 'user_id', 'evidence', 'resolved_at'];

    protected $casts = ['evidence' => 'array', 'resolved_at' => 'datetime'];
}
