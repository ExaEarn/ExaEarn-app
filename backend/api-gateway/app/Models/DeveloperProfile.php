<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeveloperProfile extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'onboarding_status',
        'default_project_id',
        'developer_terms_accepted_at',
        'last_login_at',
        'developer_type',
        'use_case',
        'default_organization_id',
    ];

    protected $casts = [
        'developer_terms_accepted_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultProject(): BelongsTo
    {
        return $this->belongsTo(DeveloperProject::class, 'default_project_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(DeveloperOrganization::class, 'default_organization_id');
    }
}
