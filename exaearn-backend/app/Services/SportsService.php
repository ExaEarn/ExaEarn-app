<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\CalculateCompetitionScoresJob;
use App\Jobs\CalculateRewardJob;
use App\Jobs\DistributeCompetitionRewardsJob;
use App\Jobs\UpdateSportsLeaderboardJob;
use App\Models\Athlete;
use App\Models\AthleteLeaderboard;
use App\Models\Competition;
use App\Models\CompetitionParticipant;
use App\Models\ScoutingInquiry;
use App\Models\Sponsorship;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SportsService
{
    public function athleteDirectory(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Athlete::query()
            ->with(['user:id,name,email', 'leaderboard'])
            ->where('is_searchable', true);

        if (!empty($filters['sport'])) {
            $query->where('sport', $filters['sport']);
        }

        if (!empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('display_name', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%")
                    ->orWhere('club', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['verified_only'])) {
            $query->where('identity_verified', true);
        }

        return $query->orderByDesc('identity_verified')
            ->orderBy('display_name')
            ->paginate($perPage);
    }

    public function athleteProfile(int $athleteId): Athlete
    {
        return Athlete::query()
            ->with([
                'user:id,name,email',
                'leaderboard',
                'competitionEntries.competition',
                'sponsorships.sponsor:id,name,email',
            ])
            ->findOrFail($athleteId);
    }

    public function createOrUpdateAthleteProfile(User $user, array $payload): Athlete
    {
        if (!in_array($user->role, ['athlete', 'admin'], true)) {
            throw new RuntimeException('Only athletes can manage athlete profiles.');
        }

        return Athlete::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => $payload['display_name'],
                'sport' => $payload['sport'],
                'country' => $payload['country'],
                'age' => $payload['age'],
                'position' => $payload['position'] ?? null,
                'club' => $payload['club'] ?? null,
                'profile_photo' => $payload['profile_photo'] ?? null,
                'highlight_video' => $payload['highlight_video'] ?? null,
                'performance_statistics' => $payload['performance_statistics'] ?? [],
                'identity_metadata' => $payload['identity_metadata'] ?? null,
                'identity_verified' => (bool) ($payload['identity_verified'] ?? false),
                'is_searchable' => (bool) ($payload['is_searchable'] ?? true),
            ]
        );
    }

    public function competitions(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Competition::query()
            ->withCount('participants')
            ->when(!empty($filters['sport']), fn (Builder $q) => $q->where('sport', $filters['sport']))
            ->when(!empty($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->orderByDesc('start_date')
            ->paginate($perPage);
    }

    public function createCompetition(User $user, array $payload): Competition
    {
        if (!in_array($user->role, ['admin', 'organizer'], true)) {
            throw new RuntimeException('Only organizers can create competitions.');
        }

        if ($this->compare((string) $payload['reward_pool'], (string) config('sports.max_reward_per_competition', '10000')) > 0) {
            throw new RuntimeException('Competition reward pool exceeds the configured maximum.');
        }

        return Competition::query()->create([
            'created_by' => $user->id,
            'title' => $payload['title'],
            'sport' => $payload['sport'],
            'description' => $payload['description'] ?? null,
            'start_date' => $payload['start_date'],
            'end_date' => $payload['end_date'],
            'status' => $payload['status'] ?? 'draft',
            'reward_pool' => $payload['reward_pool'],
            'manual_review_required' => (bool) ($payload['manual_review_required'] ?? true),
            'metadata' => $payload['metadata'] ?? null,
        ]);
    }

    public function registerAthleteForCompetition(User $user, int $competitionId, ?int $athleteId = null): CompetitionParticipant
    {
        $competition = Competition::query()->findOrFail($competitionId);
        $athlete = Athlete::query()
            ->when($athleteId, fn (Builder $q) => $q->whereKey($athleteId), fn (Builder $q) => $q->where('user_id', $user->id))
            ->firstOrFail();

        if ($competition->sport !== $athlete->sport) {
            throw new RuntimeException('Athlete sport does not match competition sport.');
        }

        return CompetitionParticipant::query()->firstOrCreate(
            [
                'competition_id' => $competition->id,
                'athlete_id' => $athlete->id,
            ],
            [
                'status' => 'registered',
            ]
        );
    }

    public function submitCompetitionScores(User $user, int $competitionId, array $scoreCards): void
    {
        if (!in_array($user->role, ['admin', 'organizer'], true)) {
            throw new RuntimeException('Only organizers can submit competition scores.');
        }

        $competition = Competition::query()->findOrFail($competitionId);
        if (!in_array($competition->status, ['active', 'review'], true)) {
            throw new RuntimeException('Competition is not accepting score updates.');
        }

        DB::transaction(function () use ($competition, $scoreCards): void {
            foreach ($scoreCards as $card) {
                $participant = CompetitionParticipant::query()
                    ->where('competition_id', $competition->id)
                    ->where('athlete_id', (int) $card['athlete_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $participant->score = (string) $card['score'];
                $participant->community_votes = (int) ($card['community_votes'] ?? $participant->community_votes);
                $participant->verification_metadata = array_merge($participant->verification_metadata ?? [], [
                    'review_notes' => $card['review_notes'] ?? null,
                    'manual_verified' => (bool) ($card['manual_verified'] ?? false),
                ]);
                $participant->verified_at = (bool) ($card['manual_verified'] ?? false) ? now() : $participant->verified_at;
                $participant->status = 'scored';
                $participant->save();
            }
        });

        CalculateCompetitionScoresJob::dispatch($competitionId)->onQueue('sports');
    }

    public function recalculateCompetitionScores(int $competitionId): void
    {
        $competition = Competition::query()->with('participants')->findOrFail($competitionId);
        $participants = $competition->participants()
            ->with('athlete')
            ->orderByDesc(DB::raw('score + community_votes'))
            ->orderByDesc('score')
            ->get();

        $rank = 1;
        foreach ($participants as $participant) {
            $participant->ranking = $rank++;
            $participant->status = $participant->score >= (float) config('sports.manual_review_score_threshold', '95')
                && $competition->manual_review_required
                && !$participant->verified_at
                ? 'pending_review'
                : 'verified';
            $participant->save();
        }

        $competition->status = 'scored';
        $competition->save();

        UpdateSportsLeaderboardJob::dispatch($competitionId)->onQueue('sports');
    }

    public function finalizeCompetition(User $user, int $competitionId): Competition
    {
        if (!in_array($user->role, ['admin', 'organizer'], true)) {
            throw new RuntimeException('Only organizers can finalize competitions.');
        }

        $competition = Competition::query()->findOrFail($competitionId);
        if ($competition->status !== 'scored') {
            throw new RuntimeException('Competition must be scored before finalization.');
        }

        $competition->status = 'completed';
        $competition->save();

        DistributeCompetitionRewardsJob::dispatch($competitionId)->onQueue('sports');

        return $competition->fresh();
    }

    public function competitionLeaderboard(int $competitionId): Collection
    {
        return CompetitionParticipant::query()
            ->with('athlete')
            ->where('competition_id', $competitionId)
            ->orderBy('ranking')
            ->get();
    }

    public function createSponsorship(User $user, array $payload): Sponsorship
    {
        if (!in_array($user->role, ['admin', 'sponsor'], true)) {
            throw new RuntimeException('Only sponsors can create sponsorships.');
        }

        $sponsorship = Sponsorship::query()->create([
            'sponsor_id' => $user->id,
            'athlete_id' => $payload['athlete_id'],
            'competition_id' => $payload['competition_id'] ?? null,
            'amount' => $payload['amount'],
            'status' => $payload['status'] ?? 'pending',
            'milestone' => $payload['milestone'] ?? null,
            'message' => $payload['message'] ?? null,
            'metadata' => $payload['metadata'] ?? null,
        ]);

        UpdateSportsLeaderboardJob::dispatch(null, $payload['athlete_id'])->onQueue('sports');

        return $sponsorship;
    }

    public function updateSponsorshipStatus(User $user, int $sponsorshipId, string $status): Sponsorship
    {
        if (!in_array($user->role, ['admin', 'sponsor'], true)) {
            throw new RuntimeException('Only sponsors can update sponsorships.');
        }

        $sponsorship = Sponsorship::query()->findOrFail($sponsorshipId);
        if ($user->role !== 'admin' && $sponsorship->sponsor_id !== $user->id) {
            throw new RuntimeException('You cannot manage this sponsorship.');
        }

        $sponsorship->status = $status;
        $sponsorship->save();

        if (in_array($status, ['active', 'completed'], true)) {
            CalculateRewardJob::dispatch(
                (int) $sponsorship->athlete->user_id,
                'sports_sponsorship_milestone',
                '1',
                [
                    'activity_key' => "sponsorship:{$sponsorship->id}:{$status}",
                    'sponsorship_id' => $sponsorship->id,
                    'athlete_id' => $sponsorship->athlete_id,
                ]
            )->onQueue('rewards');
        }

        UpdateSportsLeaderboardJob::dispatch(null, $sponsorship->athlete_id)->onQueue('sports');

        return $sponsorship;
    }

    public function createInquiry(User $user, array $payload): ScoutingInquiry
    {
        if (!in_array($user->role, ['admin', 'scout', 'sponsor', 'organizer'], true)) {
            throw new RuntimeException('Your role cannot send scouting inquiries.');
        }

        return ScoutingInquiry::query()->create([
            'sender_user_id' => $user->id,
            'athlete_id' => $payload['athlete_id'],
            'sender_role' => $user->role,
            'subject' => $payload['subject'],
            'message' => $payload['message'],
            'status' => 'open',
            'metadata' => $payload['metadata'] ?? null,
        ]);
    }

    public function refreshAthleteLeaderboard(?int $competitionId = null, ?int $athleteId = null): void
    {
        $athleteIds = collect();

        if ($competitionId) {
            $athleteIds = $athleteIds->merge(
                CompetitionParticipant::query()->where('competition_id', $competitionId)->pluck('athlete_id')
            );
        }

        if ($athleteId) {
            $athleteIds->push($athleteId);
        }

        $athleteIds = $athleteIds->filter()->unique()->values();

        Athlete::query()
            ->whereIn('id', $athleteIds)
            ->get()
            ->each(function (Athlete $athlete): void {
                $wins = CompetitionParticipant::query()
                    ->where('athlete_id', $athlete->id)
                    ->where('ranking', 1)
                    ->count();

                $performanceScore = (string) CompetitionParticipant::query()
                    ->where('athlete_id', $athlete->id)
                    ->sum('score');

                $communityVotes = (int) CompetitionParticipant::query()
                    ->where('athlete_id', $athlete->id)
                    ->sum('community_votes');

                $sponsorshipCount = Sponsorship::query()
                    ->where('athlete_id', $athlete->id)
                    ->where('status', 'active')
                    ->count();

                $sponsorshipTotal = (string) Sponsorship::query()
                    ->where('athlete_id', $athlete->id)
                    ->where('status', 'active')
                    ->sum('amount');

                AthleteLeaderboard::query()->updateOrCreate(
                    ['athlete_id' => $athlete->id],
                    [
                        'sport' => $athlete->sport,
                        'competition_wins' => $wins,
                        'performance_score' => $performanceScore,
                        'community_votes' => $communityVotes,
                        'sponsorship_count' => $sponsorshipCount,
                        'sponsorship_total' => $sponsorshipTotal,
                        'updated_at' => now(),
                    ]
                );
            });
    }

    public function topAthletes(?string $sport = null, int $limit = 25): Collection
    {
        return AthleteLeaderboard::query()
            ->with('athlete')
            ->when($sport, fn (Builder $q) => $q->where('sport', $sport))
            ->orderByDesc('performance_score')
            ->orderByDesc('competition_wins')
            ->orderByDesc('community_votes')
            ->limit($limit)
            ->get();
    }

    private function compare(string $left, string $right): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, 8);
        }

        $leftFloat = (float) $left;
        $rightFloat = (float) $right;

        return $leftFloat < $rightFloat ? -1 : ($leftFloat > $rightFloat ? 1 : 0);
    }
}
