<?php

declare(strict_types=1);

namespace App\Services\PersonalizedContent;

use App\Models\PersonalizedContent;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ProductEventContentService
{
    public function ingest(string $eventName, string $eventId, array $payload): PersonalizedContent
    {
        $mapping = ((array) config('personalized_content.event_registry'))[$eventName] ?? null;
        if (! is_array($mapping)) throw new InvalidArgumentException('Product event is not approved for content generation.');
        foreach (['title', 'body'] as $required) if (trim((string) ($payload[$required] ?? '')) === '') throw new InvalidArgumentException("Event payload requires {$required}.");
        $key = 'product-event:'.hash('sha256', $eventName.':'.$eventId);
        return PersonalizedContent::query()->firstOrCreate(['idempotency_key' => $key], [
            'content_uuid' => (string) Str::uuid(), 'type' => $mapping['type'], 'source_type' => 'PRODUCT_EVENT', 'source_id' => $eventId,
            'title' => strip_tags((string) $payload['title']), 'subtitle' => isset($payload['subtitle']) ? strip_tags((string) $payload['subtitle']) : null,
            'body' => strip_tags((string) $payload['body']), 'badge' => $mapping['badge'], 'cta_label' => $mapping['cta'], 'cta_route' => $mapping['route'],
            'related_product' => $mapping['product'] ?? ($payload['product'] ?? null), 'related_asset' => isset($payload['asset']) ? strtoupper((string) $payload['asset']) : null,
            'related_entity_type' => $payload['entity_type'] ?? null, 'related_entity_id' => $payload['entity_id'] ?? null,
            'target_interests' => $payload['target_interests'] ?? [], 'target_products' => array_values(array_filter([$mapping['product'] ?? null])), 'target_assets' => array_values(array_filter([$payload['asset'] ?? null])),
            'priority' => min(100, max(0, (int) ($payload['priority'] ?? 50))), 'personalization_weight' => 50, 'frequency_cap' => 5,
            'status' => 'PUBLISHED', 'publish_at' => now(), 'expires_at' => $payload['expires_at'] ?? null,
        ]);
    }
}
