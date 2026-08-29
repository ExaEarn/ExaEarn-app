<?php

declare(strict_types=1);

namespace App\Services\PersonalizedContent;

use App\Models\PersonalizedContent;
use App\Models\User;

final class ContentRankingService
{
    public function score(PersonalizedContent $content, User $user): float
    {
        $weights = (array) config('personalized_content.weights', []);
        $dashboard = (array) data_get($user->preferences, 'dashboard', []);
        $primary = strtolower((string) ($dashboard['primary_goal'] ?? $dashboard['primary_interest'] ?? ''));
        $interests = array_map('strtolower', array_merge((array) ($dashboard['interests'] ?? []), (array) ($dashboard['selected_interests'] ?? [])));
        $targets = array_map('strtolower', $content->target_interests ?? []);
        $products = array_map('strtolower', $content->target_products ?? []);
        $watchlist = array_map('strtoupper', (array) data_get($user->preferences, 'watchlist.assets', []));
        $mode = strtolower((string) ($dashboard['selected_mode'] ?? 'lite'));
        $score = (float) $content->personalization_weight + ((float) $content->priority * (float) ($weights['priority_scale'] ?? .5));
        if ($primary !== '' && in_array($primary, $targets, true)) $score += (float) ($weights['primary_interest'] ?? 32);
        $score += count(array_intersect($interests, $targets)) * (float) ($weights['secondary_interest'] ?? 18);
        if ($products !== [] && array_intersect($interests, $products) !== []) $score += (float) ($weights['product'] ?? 22);
        if ($content->related_asset && in_array(strtoupper($content->related_asset), $watchlist, true)) $score += (float) ($weights['asset'] ?? 18);
        $modes = array_map('strtolower', $content->target_experience_modes ?? []);
        if ($modes === [] || in_array($mode, $modes, true)) $score += (float) ($weights['experience_mode'] ?? 8);
        $ageHours = max(0, now()->diffInHours($content->publish_at ?? $content->created_at));
        $score += max(0, (float) ($weights['freshness'] ?? 20) - min(20, $ageHours / 12));
        return round($score, 3);
    }
}
