<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionalReport extends Model
{
    protected $fillable = ['report_uuid', 'institution_id', 'requested_by_user_id', 'report_type', 'status', 'period_start', 'period_end', 'filters', 'summary'];

    protected $casts = ['period_start' => 'datetime', 'period_end' => 'datetime', 'filters' => 'array', 'summary' => 'array'];
}
