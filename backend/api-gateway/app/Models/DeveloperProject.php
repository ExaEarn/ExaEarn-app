<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeveloperProject extends Model
{
    protected $fillable = ['project_uuid', 'user_id', 'workspace_id', 'organization_id', 'created_by', 'name', 'description', 'environment', 'status', 'archived_at', 'tier', 'settings'];

    protected $casts = ['settings' => 'array', 'archived_at' => 'datetime'];

    public function apiKeys(): HasMany
    {
        return $this->hasMany(DeveloperApiKey::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo { return $this->belongsTo(DeveloperWorkspace::class, 'workspace_id'); }
    public function organization(): BelongsTo { return $this->belongsTo(DeveloperOrganization::class, 'organization_id'); }
    public function environments(): HasMany { return $this->hasMany(DeveloperProjectEnvironment::class, 'project_id'); }
}
