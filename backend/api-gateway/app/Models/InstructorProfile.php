<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructorProfile extends Model
{
    protected $fillable = ['user_id', 'display_name', 'headline', 'bio', 'expertise', 'portfolio_links', 'status', 'approved_at', 'legal_name', 'entity_type', 'country', 'tax_residency', 'tax_identifier_hash', 'tax_status', 'withholding_status', 'tax_policy_version', 'tax_verification_status', 'tax_documents'];

    protected $casts = [
        'expertise' => 'array',
        'portfolio_links' => 'array',
        'approved_at' => 'datetime',
        'tax_documents' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
