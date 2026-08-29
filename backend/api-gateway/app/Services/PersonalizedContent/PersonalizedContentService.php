<?php

declare(strict_types=1);

namespace App\Services\PersonalizedContent;

use App\Models\PersonalizedContent;
use App\Models\PersonalizedContentInteraction;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class PersonalizedContentService
{
    public function __construct(private readonly ContentEligibilityService $eligibility, private readonly ContentRankingService $ranking) {}

    public function dashboard(User $user): Collection
    {
        return $this->ranked($user)->take((int) config('personalized_content.dashboard_limit', 5))->values();
    }

    public function feed(User $user, int $page = 1, ?string $type = null): LengthAwarePaginator
    {
        $items = $this->ranked($user, $type);
        $perPage = (int) config('personalized_content.feed_limit', 20);
        return new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]);
    }

    public function interact(PersonalizedContent $content, User $user, string $type, array $context): PersonalizedContentInteraction
    {
        return PersonalizedContentInteraction::query()->firstOrCreate(
            ['event_uuid' => $context['event_uuid'] ?? (string) Str::uuid()],
            ['content_id' => $content->id, 'user_id' => $user->id, 'interaction_type' => $type, 'surface' => $context['surface'] ?? 'DASHBOARD', 'position' => $context['position'] ?? null, 'metadata' => array_diff_key($context, array_flip(['event_uuid', 'surface', 'position']))]
        );
    }

    private function ranked(User $user, ?string $type = null): Collection
    {
        $dismissed = PersonalizedContentInteraction::query()->where('user_id', $user->id)->where('interaction_type', 'DISMISS')->pluck('content_id');
        $shown = PersonalizedContentInteraction::query()->where('user_id', $user->id)->where('interaction_type', 'IMPRESSION')->selectRaw('content_id, count(*) as aggregate')->groupBy('content_id')->pluck('aggregate', 'content_id');
        return PersonalizedContent::query()
            ->whereIn('status', ['PUBLISHED', 'SCHEDULED'])
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($type, fn ($q) => $q->where('type', strtoupper($type)))
            ->whereNotIn('id', $dismissed)
            ->limit(500)->get()
            ->filter(fn (PersonalizedContent $item) => ((int) ($shown[$item->id] ?? 0) < (int) $item->frequency_cap) && $this->eligibility->eligible($item, $user))
            ->map(function (PersonalizedContent $item) use ($user): PersonalizedContent { $item->setAttribute('relevance_score', $this->ranking->score($item, $user)); return $item; })
            ->sortByDesc(fn (PersonalizedContent $item) => [$item->relevance_score, $item->priority, $item->publish_at?->timestamp ?? 0])
            ->values();
    }
}
