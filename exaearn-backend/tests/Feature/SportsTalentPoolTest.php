<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Competition;
use App\Models\CompetitionParticipant;
use App\Models\User;
use App\Services\SportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SportsTalentPoolTest extends TestCase
{
    use RefreshDatabase;

    public function test_athlete_can_create_profile(): void
    {
        $user = User::factory()->create(['role' => 'athlete']);

        $response = $this->actingAs($user)->postJson('/api/sports/athletes/profile', [
            'display_name' => 'Kofi Mensah',
            'sport' => 'Football',
            'country' => 'Ghana',
            'age' => 21,
            'position' => 'Winger',
            'club' => 'Accra Stars',
            'performance_statistics' => ['matches' => 20, 'goals' => 8],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('athletes', [
            'user_id' => $user->id,
            'sport' => 'Football',
        ]);
    }

    public function test_organizer_can_create_and_score_competition(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $athleteUser = User::factory()->create(['role' => 'athlete']);
        $athlete = Athlete::query()->create([
            'user_id' => $athleteUser->id,
            'display_name' => 'Amina Yusuf',
            'sport' => 'Football',
            'country' => 'Nigeria',
            'age' => 20,
        ]);

        $competition = Competition::query()->create([
            'created_by' => $organizer->id,
            'title' => 'Elite Regional Cup',
            'sport' => 'Football',
            'start_date' => now(),
            'end_date' => now()->addDay(),
            'status' => 'active',
            'reward_pool' => '100',
        ]);

        CompetitionParticipant::query()->create([
            'competition_id' => $competition->id,
            'athlete_id' => $athlete->id,
        ]);

        $response = $this->actingAs($organizer)->postJson("/api/sports/competitions/{$competition->id}/scores", [
            'scores' => [
                [
                    'athlete_id' => $athlete->id,
                    'score' => 88,
                    'community_votes' => 10,
                    'manual_verified' => true,
                ],
            ],
        ]);

        $response->assertAccepted();
    }

    public function test_sports_service_refreshes_leaderboard(): void
    {
        $user = User::factory()->create(['role' => 'athlete']);
        $athlete = Athlete::query()->create([
            'user_id' => $user->id,
            'display_name' => 'Samuel Osei',
            'sport' => 'Football',
            'country' => 'Ghana',
            'age' => 22,
        ]);

        CompetitionParticipant::query()->create([
            'competition_id' => Competition::query()->create([
                'created_by' => $user->id,
                'title' => 'Showcase Cup',
                'sport' => 'Football',
                'start_date' => now(),
                'end_date' => now()->addDay(),
                'status' => 'completed',
                'reward_pool' => '50',
            ])->id,
            'athlete_id' => $athlete->id,
            'score' => 90,
            'ranking' => 1,
            'community_votes' => 12,
        ]);

        /** @var SportsService $service */
        $service = app(SportsService::class);
        $service->refreshAthleteLeaderboard(null, $athlete->id);

        $this->assertDatabaseHas('athlete_leaderboards', [
            'athlete_id' => $athlete->id,
            'competition_wins' => 1,
            'community_votes' => 12,
        ]);
    }
}
