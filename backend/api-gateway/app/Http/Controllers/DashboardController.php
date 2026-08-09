<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\DashboardExperienceRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
final class DashboardController extends Controller
{
    public function insights(): JsonResponse
    {
        $counts = array_fill_keys(DashboardExperienceRegistry::KEYS, 0);
        $primary = array_fill_keys(DashboardExperienceRegistry::KEYS, 0);
        $personalized = 0;
        User::query()->select(['id', 'preferences'])->chunkById(500, function ($users) use (&$counts, &$primary, &$personalized): void {
            foreach ($users as $user) {
                $dashboard = (array) (($user->preferences ?? [])['dashboard'] ?? []);
                if (($dashboard['mode'] ?? 'all') !== 'personalized') continue;
                $personalized++;
                foreach ((array) ($dashboard['selected_interests'] ?? []) as $key) if (array_key_exists($key, $counts)) $counts[$key]++;
                $key = $dashboard['primary_interest'] ?? null;
                if ($key && array_key_exists($key, $primary)) $primary[$key]++;
            }
        });
        $total = User::query()->count();
        arsort($primary);
        return response()->json(['status' => 'success', 'data' => ['total_users' => $total, 'personalized_users' => $personalized, 'default_or_skipped_users' => max(0, $total - $personalized), 'completion_rate' => $total ? round($personalized / $total * 100, 2) : 0, 'feature_selections' => $counts, 'primary_experiences' => $primary]]);
    }
    public function show(Request $request, DashboardExperienceRegistry $registry): JsonResponse
    {
        $defaults = ['mode' => 'all', 'primary_interest' => null, 'selected_interests' => [], 'hidden_widgets' => [], 'widget_order' => [], 'onboarding_completed' => false];
        $userId = (int) $request->user()->id;
        $state = [
            'crypto_exchange' => ['open_orders' => $this->count('orders', $userId, 'user_id', 'status', ['open', 'pending'])],
            'exaai' => ['active_sessions' => $this->count('exa_ai_sessions', $userId, 'user_id', 'status', ['active', 'paused'])],
            'earn' => ['active_positions' => $this->count('staking_positions', $userId, 'user_id', 'status', ['active', 'pending', 'unstaking'])],
            'giftcards' => ['pending_orders' => $this->count('giftcard_orders', $userId, 'user_id', 'status', ['pending', 'pending_analysis', 'pending_review', 'flagged'])],
            'games' => ['recent_bets' => $this->count('flight_game_bets', $userId)],
            'exaskills' => ['active_courses' => $this->count('course_enrollments', $userId, 'user_id', 'completed', [false, 0])],
            'crowdfund' => [],
            'nft_marketplace' => ['owned_assets' => $this->count('nfts', $userId, 'owner_id')],
            'agritech' => ['active_projects' => $this->count('farm_investments', $userId, 'user_id', 'status', ['locked', 'active'])],
        ];
        return response()->json(['status' => 'success', 'data' => ['preferences' => array_merge($defaults, (array) (($request->user()->preferences ?? [])['dashboard'] ?? [])), 'experiences' => $registry->all(), 'state' => $state, 'critical_alerts' => $this->criticalAlerts($userId)]]);
    }
    private function criticalAlerts(int $userId): array
    {
        $alerts = collect();
        if (Schema::hasTable('notifications')) {
            $alerts = DB::table('notifications')->where('user_id', $userId)->where('status', '!=', 'read')->whereIn('type', ['security', 'withdrawal', 'deposit', 'kyc', 'account_restriction', 'transaction_failed'])->latest('created_at')->limit(5)->get()->map(fn ($item) => ['id' => 'notification-'.$item->id, 'kind' => (string) $item->type, 'title' => (string) $item->title, 'message' => (string) $item->message, 'created_at' => $item->created_at]);
        }
        if (Schema::hasTable('transactions')) {
            $failed = DB::table('transactions')->where('user_id', $userId)->where('status', 'failed')->latest('created_at')->limit(3)->get()->map(fn ($item) => ['id' => 'transaction-'.$item->id, 'kind' => 'transaction_failed', 'title' => 'Transaction needs attention', 'message' => ucfirst((string) $item->type).' could not be completed.', 'created_at' => $item->created_at]);
            $alerts = $alerts->concat($failed);
        }
        return $alerts->sortByDesc('created_at')->take(5)->values()->all();
    }
    private function count(string $table, int $userId, string $userColumn = 'user_id', ?string $filterColumn = null, array $filterValues = []): int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $userColumn)) return 0;
        $query = DB::table($table)->where($userColumn, $userId);
        if ($filterColumn && Schema::hasColumn($table, $filterColumn)) $query->whereIn($filterColumn, $filterValues);
        return $query->count();
    }
}
