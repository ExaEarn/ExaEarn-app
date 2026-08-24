<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingTestRun extends Model
{
    protected $fillable = ['test_run_uuid', 'application_id', 'requested_by_admin_id', 'environment', 'overall_status', 'results', 'critical_failures', 'completed_at'];

    protected $casts = ['results' => 'array', 'critical_failures' => 'array', 'completed_at' => 'datetime'];
}
