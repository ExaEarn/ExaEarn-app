<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonalizedContent extends Model
{
    protected $fillable = [
        'content_uuid', 'type', 'source_type', 'source_id', 'source_provider', 'idempotency_key',
        'title', 'subtitle', 'body', 'image_url', 'icon', 'badge', 'cta_label', 'cta_route', 'cta_payload',
        'related_product', 'related_asset', 'related_entity_type', 'related_entity_id', 'priority', 'severity',
        'status', 'target_interests', 'target_products', 'target_assets', 'target_experience_modes',
        'target_regions', 'target_countries', 'target_user_segments', 'minimum_kyc_tier', 'eligibility_rules',
        'personalization_weight', 'frequency_cap', 'publish_at', 'expires_at', 'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'cta_payload' => 'array', 'target_interests' => 'array', 'target_products' => 'array',
            'target_assets' => 'array', 'target_experience_modes' => 'array', 'target_regions' => 'array',
            'target_countries' => 'array', 'target_user_segments' => 'array', 'eligibility_rules' => 'array',
            'publish_at' => 'datetime', 'expires_at' => 'datetime',
        ];
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(PersonalizedContentInteraction::class, 'content_id');
    }
}
