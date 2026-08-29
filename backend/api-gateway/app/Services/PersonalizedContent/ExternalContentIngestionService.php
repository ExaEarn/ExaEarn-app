<?php

declare(strict_types=1);

namespace App\Services\PersonalizedContent;

use App\Contracts\TrustedExternalContentAdapter;
use App\Models\PersonalizedContent;
use Illuminate\Support\Str;

final class ExternalContentIngestionService
{
    public function ingest(TrustedExternalContentAdapter $adapter): int
    {
        $count = 0;
        foreach ($adapter->fetch() as $row) {
            if (! is_array($row) || empty($row['external_id']) || empty($row['title']) || empty($row['body'])) continue;
            $route = $row['cta_route'] ?? 'market';
            $type = $row['type'] ?? 'MARKET_NEWS';
            if (! in_array($route, config('personalized_content.routes'), true) || ! in_array($type, config('personalized_content.types'), true)) continue;
            $record = PersonalizedContent::query()->firstOrCreate(['idempotency_key' => 'external:'.$adapter->provider().':'.$row['external_id']], [
                'content_uuid' => (string) Str::uuid(), 'type' => $type, 'source_type' => 'EXTERNAL', 'source_id' => (string) $row['external_id'], 'source_provider' => $adapter->provider(),
                'title' => strip_tags((string) $row['title']), 'body' => strip_tags((string) $row['body']), 'cta_label' => $row['cta_label'] ?? 'View Market', 'cta_route' => $route,
                'related_asset' => isset($row['asset']) ? strtoupper((string) $row['asset']) : null, 'status' => 'PUBLISHED', 'priority' => min(70, (int) ($row['priority'] ?? 40)), 'personalization_weight' => 40, 'frequency_cap' => 3,
                'publish_at' => $row['published_at'] ?? now(), 'expires_at' => $row['expires_at'] ?? now()->addDays(7),
            ]);
            if ($record->wasRecentlyCreated) $count++;
        }
        return $count;
    }
}
