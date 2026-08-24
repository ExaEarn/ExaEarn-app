<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeveloperProject extends Model
{
    protected $fillable = ['project_uuid', 'user_id', 'name', 'description', 'environment', 'status', 'tier', 'settings'];

    protected $casts = ['settings' => 'array'];

    public function apiKeys(): HasMany
    {
        return $this->hasMany(DeveloperApiKey::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
