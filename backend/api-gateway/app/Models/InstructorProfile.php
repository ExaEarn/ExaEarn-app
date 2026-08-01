<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructorProfile extends Model
{
    protected $fillable = ['user_id', 'display_name', 'headline', 'bio', 'expertise', 'portfolio_links', 'status', 'approved_at'];

    protected $casts = [
        'expertise' => 'array',
        'portfolio_links' => 'array',
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
