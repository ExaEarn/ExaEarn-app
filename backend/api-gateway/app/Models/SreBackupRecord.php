<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SreBackupRecord extends Model
{
    protected $guarded = [];
    protected $casts = ['encrypted' => 'boolean', 'metadata' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'retention_until' => 'datetime', 'restore_tested_at' => 'datetime'];
}
