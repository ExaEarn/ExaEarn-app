<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\AgriReward;
use App\Models\Athlete;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\FarmingProject;
use App\Models\Giftcard;
use App\Models\GiftcardOrder;
use App\Models\LedgerEntry;
use App\Models\LotteryGame;
use App\Models\LotteryResult;
use App\Models\Market;
use App\Models\Nft;
use App\Models\NftSale;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Permission;
use App\Models\RewardActivity;
use App\Models\Role;
use App\Models\Setting;
use App\Models\StakingPool;
use App\Models\Trade;
use App\Models\Transaction;
use App\Models\TreasuryBalance;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Models\UserReward;
use App\Models\Wallet;
use App\Services\AdminAuditService;
use App\Services\LedgerService;
use App\Services\NotificationService;
use App\Services\PermissionService;
use App\Services\TransactionService;
use App\Services\Treasury\TreasuryService as CryptoTreasuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class AdminPlatformController extends Controller
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly PermissionService $permissionService,
        private readonly LedgerService $ledger,
        private readonly TransactionService $transactions,
        private readonly NotificationService $notifications,
        private readonly CryptoTreasuryService $treasuryService,
    ) {
    }

    public function users(Request $request): JsonResponse
    {
        return response()->json(['data' => User::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function user(int $id): JsonResponse
    {
        return response()->json(['data' => User::query()->with(['wallets', 'transactions'])->findOrFail($id)]);
    }

    public function freezeUser(Request $request): JsonResponse
    {
        $payload = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'reason' => ['nullable', 'string']]);
        $user = User::query()->findOrFail((int) $payload['user_id']);
        $user->withdrawal_locked_until = now()->addYears(10);
        $user->save();
        $this->log($request, 'admin.user.freeze', $payload);

        return response()->json(['data' => $user]);
    }

    public function unfreezeUser(Request $request): JsonResponse
    {
        $payload = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $user = User::query()->findOrFail((int) $payload['user_id']);
        $user->withdrawal_locked_until = null;
        $user->save();
        $this->log($request, 'admin.user.unfreeze', $payload);

        return response()->json(['data' => $user]);
    }

    public function adjustUserBalance(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'asset' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
            'confirmation' => ['required', 'accepted'],
        ]);

        $amount = (string) abs((float) $payload['amount']);
        $reference = 'admin_adjust:' . Str::uuid();

        if ((float) $payload['amount'] > 0) {
            $tx = $this->ledger->credit((int) $payload['user_id'], $amount, (string) $payload['asset'], $reference, (string) $payload['reason']);
        } else {
            $tx = $this->ledger->debit((int) $payload['user_id'], $amount, (string) $payload['asset'], $reference, (string) $payload['reason']);
        }

        $this->log($request, 'admin.user.balance_adjust', array_merge($payload, ['reference' => $reference]));

        return response()->json(['data' => $tx], 201);
    }

    public function userLogs(Request $request): JsonResponse
    {
        return response()->json(['data' => AuditLog::query()->where('user_id', $request->query('user_id'))->latest()->paginate(25)]);
    }

    public function userWallets(Request $request): JsonResponse
    {
        return response()->json(['data' => Wallet::query()->where('user_id', $request->query('user_id'))->latest()->paginate(25)]);
    }

    public function userTrades(Request $request): JsonResponse
    {
        return response()->json(['data' => Trade::query()->whereHas('buyOrder', fn ($q) => $q->where('user_id', $request->query('user_id')))->orWhereHas('sellOrder', fn ($q) => $q->where('user_id', $request->query('user_id')))->latest()->paginate(25)]);
    }

    public function userRewards(Request $request): JsonResponse
    {
        return response()->json(['data' => UserReward::query()->where('user_id', $request->query('user_id'))->latest()->paginate(25)]);
    }

    public function wallets(Request $request): JsonResponse
    {
        return response()->json(['data' => Wallet::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function freezeWallet(Request $request): JsonResponse
    {
        $payload = $request->validate(['wallet_id' => ['required', 'integer', 'exists:wallets,id'], 'reason' => ['nullable', 'string']]);
        $wallet = Wallet::query()->findOrFail((int) $payload['wallet_id']);
        $wallet->locked_balance = bcadd((string) $wallet->locked_balance, (string) $wallet->available_balance, 8);
        $wallet->available_balance = '0';
        $wallet->save();
        $this->log($request, 'admin.wallet.freeze', $payload);

        return response()->json(['data' => $wallet]);
    }

    public function adjustWallet(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'asset' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
            'confirmation' => ['required', 'accepted'],
        ]);

        $wallet = Wallet::query()->findOrFail((int) $payload['wallet_id']);
        $asset = strtoupper((string) $payload['asset']);

        if (strtoupper($wallet->currency) !== $asset) {
            return response()->json(['message' => 'Wallet asset does not match requested asset.'], 422);
        }

        $request->merge(['user_id' => $wallet->user_id, 'asset' => $asset]);
        $response = $this->adjustUserBalance($request);
        $this->log($request, 'admin.wallet.balance_adjust', array_merge($payload, ['wallet_id' => $wallet->id]));

        return $response;
    }

    public function transactions(Request $request): JsonResponse
    {
        return response()->json(['data' => Transaction::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function pairs(Request $request): JsonResponse
    {
        return response()->json(['data' => Market::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function createPair(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'base_currency' => ['required', 'string', 'max:16'],
            'quote_currency' => ['required', 'string', 'max:16'],
            'status' => ['nullable', 'string'],
        ]);
        $market = Market::query()->updateOrCreate(['symbol' => strtoupper($payload['symbol'])], array_merge($payload, ['symbol' => strtoupper($payload['symbol'])]));
        $this->log($request, 'admin.market.upsert', $payload);

        return response()->json(['data' => $market], 201);
    }

    public function updatePair(Request $request): JsonResponse
    {
        $payload = $request->validate(['symbol' => ['required', 'string'], 'data' => ['required', 'array']]);
        $market = Market::query()->where('symbol', strtoupper($payload['symbol']))->firstOrFail();
        $market->fill($payload['data']);
        $market->save();
        $this->log($request, 'admin.market.update', $payload);

        return response()->json(['data' => $market]);
    }

    public function disablePair(Request $request): JsonResponse
    {
        $payload = $request->validate(['symbol' => ['required', 'string']]);
        $market = Market::query()->where('symbol', strtoupper($payload['symbol']))->firstOrFail();
        $market->status = 'disabled';
        $market->save();
        $this->log($request, 'admin.market.disable', $payload);

        return response()->json(['data' => $market]);
    }

    public function orders(Request $request): JsonResponse
    {
        return response()->json(['data' => Order::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function trades(Request $request): JsonResponse
    {
        return response()->json(['data' => Trade::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function rewards(Request $request): JsonResponse
    {
        return response()->json(['data' => RewardActivity::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function upsertReward(Request $request): JsonResponse
    {
        $payload = $request->validate(['activity_type' => ['required', 'string'], 'reward_rate' => ['required', 'numeric'], 'daily_limit' => ['required', 'numeric'], 'status' => ['nullable', 'string'], 'mode' => ['nullable', 'string']]);
        $reward = RewardActivity::query()->updateOrCreate(['activity_type' => $payload['activity_type']], $payload);
        $this->log($request, 'admin.reward.upsert', $payload);

        return response()->json(['data' => $reward], 201);
    }

    public function deleteReward(int $id, Request $request): JsonResponse
    {
        RewardActivity::query()->findOrFail($id)->delete();
        $this->log($request, 'admin.reward.delete', ['id' => $id]);
        return response()->json(['message' => 'Reward deleted.']);
    }

    public function stakingPools(Request $request): JsonResponse
    {
        return response()->json(['data' => StakingPool::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function upsertStakingPool(Request $request): JsonResponse
    {
        $payload = $request->validate(['id' => ['nullable', 'integer'], 'token' => ['required_without:asset', 'string'], 'asset' => ['nullable', 'string'], 'reward_token' => ['required', 'string'], 'apr' => ['nullable', 'numeric'], 'lock_days' => ['nullable', 'integer'], 'status' => ['nullable', 'string']]);
        $pool = StakingPool::query()->updateOrCreate(['id' => $payload['id'] ?? null], [
            'asset' => $payload['asset'] ?? $payload['token'],
            'reward_token' => $payload['reward_token'],
            'lock_period' => (int) ($payload['lock_days'] ?? 0) * 86400,
            'reward_rate' => $payload['apr'] ?? 0,
            'status' => $payload['status'] ?? 'active',
        ]);
        $this->log($request, 'admin.staking.upsert', $payload);

        return response()->json(['data' => $pool], 201);
    }

    public function disableStakingPool(Request $request): JsonResponse
    {
        $payload = $request->validate(['id' => ['required', 'integer', 'exists:staking_pools,id']]);
        $pool = StakingPool::query()->findOrFail((int) $payload['id']);
        $pool->status = 'disabled';
        $pool->save();
        $this->log($request, 'admin.staking.disable', $payload);

        return response()->json(['data' => $pool]);
    }

    public function modelIndex(Request $request, string $resource): JsonResponse
    {
        $model = $this->resourceModel($resource);
        return response()->json(['data' => $model::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function modelStore(Request $request, string $resource): JsonResponse
    {
        $model = $this->resourceModel($resource);
        $record = $model::query()->create($request->all());
        $this->log($request, "admin.{$resource}.create", $request->all());
        return response()->json(['data' => $record], 201);
    }

    public function modelUpdate(Request $request, string $resource): JsonResponse
    {
        $payload = $request->validate(['id' => ['required', 'integer'], 'data' => ['required', 'array']]);
        $model = $this->resourceModel($resource);
        $record = $model::query()->findOrFail((int) $payload['id']);
        $record->fill($payload['data']);
        $record->save();
        $this->log($request, "admin.{$resource}.update", $payload);
        return response()->json(['data' => $record]);
    }

    public function modelDisable(Request $request, string $resource): JsonResponse
    {
        $payload = $request->validate(['id' => ['required', 'integer'], 'confirmation' => ['nullable']]);
        $model = $this->resourceModel($resource);
        $record = $model::query()->findOrFail((int) $payload['id']);
        $record->status = 'disabled';
        $record->save();
        $this->log($request, "admin.{$resource}.disable", $payload);
        return response()->json(['data' => $record]);
    }

    public function treasury(Request $request): JsonResponse
    {
        return response()->json(['data' => ['balances' => TreasuryBalance::query()->get(), 'recent_transactions' => TreasuryTransaction::query()->latest()->limit(25)->get()]]);
    }

    public function treasuryMove(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'asset' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'from' => ['required', 'string', 'in:hot,cold'],
            'to' => ['required', 'string', 'in:hot,cold'],
            'chain' => ['nullable', 'string'],
            'confirmation' => ['required', 'accepted'],
        ]);

        if ($payload['from'] === $payload['to']) {
            return response()->json(['message' => 'Source and destination must differ.'], 422);
        }

        $chain = $payload['chain'] ?? 'mainnet';
        $asset = strtoupper((string) $payload['asset']);
        $amount = (string) $payload['amount'];

        if ($payload['from'] === 'hot' && $payload['to'] === 'cold') {
            $transaction = $this->treasuryService->moveToCold($chain, $asset, $amount, $request->user()->id);
        } elseif ($payload['from'] === 'cold' && $payload['to'] === 'hot') {
            $transaction = $this->treasuryService->moveToHot($chain, $asset, $amount, $request->user()->id);
        } else {
            return response()->json(['message' => 'Unsupported treasury move path.'], 422);
        }

        $this->log($request, 'admin.treasury.move', array_merge($payload, ['transaction_id' => $transaction->id]));

        return response()->json(['data' => $transaction], 202);
    }

    public function approveWithdraw(Request $request): JsonResponse
    {
        $payload = $request->validate(['withdraw_request_id' => ['required', 'integer'], 'confirmation' => ['required', 'accepted']]);
        $withdrawRequest = $this->treasuryService->approveWithdraw((int) $payload['withdraw_request_id'], $request->user()->id);
        $this->log($request, 'admin.treasury.approve_withdraw', array_merge($payload, ['withdraw_request_id' => $withdrawRequest->id]));

        return response()->json(['data' => $withdrawRequest]);
    }

    public function treasuryLogs(Request $request): JsonResponse
    {
        return response()->json(['data' => TreasuryTransaction::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function logs(Request $request): JsonResponse
    {
        return response()->json(['data' => AuditLog::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function adminLogs(Request $request): JsonResponse
    {
        return response()->json(['data' => AdminLog::query()->with('admin.role')->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function securityLogs(Request $request): JsonResponse
    {
        return response()->json(['data' => AuditLog::query()->where('action', 'like', '%security%')->orWhere('action', 'like', '%auth%')->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function sendNotification(Request $request): JsonResponse
    {
        $payload = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'type' => ['required', 'string'], 'title' => ['required', 'string'], 'message' => ['required', 'string'], 'channels' => ['nullable', 'array'], 'data' => ['nullable', 'array']]);
        $notification = $this->notifications->create((int) $payload['user_id'], $payload['type'], $payload['title'], $payload['message'], $payload['channels'] ?? ['in_app'], $payload['data'] ?? null);
        $this->log($request, 'admin.notification.send', $payload);

        return response()->json(['data' => $notification], 201);
    }

    public function notifications(Request $request): JsonResponse
    {
        return response()->json(['data' => Notification::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function settings(Request $request): JsonResponse
    {
        return response()->json(['data' => Setting::query()->orderBy('group')->orderBy('key')->paginate((int) $request->query('per_page', 100))]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $payload = $request->validate(['settings' => ['required', 'array']]);
        $updated = [];
        foreach ($payload['settings'] as $key => $value) {
            $updated[] = Setting::query()->updateOrCreate(['key' => (string) $key], ['value' => is_scalar($value) ? (string) $value : json_encode($value), 'type' => is_array($value) ? 'json' : 'string', 'group' => strtok((string) $key, '.') ?: 'general']);
        }
        $this->log($request, 'admin.settings.update', ['keys' => array_keys($payload['settings'])]);
        return response()->json(['data' => $updated]);
    }

    public function admins(Request $request): JsonResponse
    {
        return response()->json(['data' => Admin::query()->with('role')->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function createAdmin(Request $request): JsonResponse
    {
        $payload = $request->validate(['name' => ['required', 'string'], 'email' => ['required', 'email', 'unique:admins,email'], 'password' => ['required', 'string', 'min:10'], 'role_id' => ['required', 'integer', 'exists:roles,id'], 'status' => ['nullable', 'string']]);
        $admin = Admin::query()->create(array_merge($payload, ['password' => Hash::make($payload['password']), 'status' => $payload['status'] ?? 'active']));
        $this->log($request, 'admin.admin.create', ['admin_id' => $admin->id, 'email' => $admin->email]);
        return response()->json(['data' => $admin->load('role')], 201);
    }

    public function roles(Request $request): JsonResponse
    {
        return response()->json(['data' => Role::query()->with('permissions')->paginate((int) $request->query('per_page', 25)), 'permissions' => Permission::query()->orderBy('name')->get()]);
    }

    public function upsertRole(Request $request): JsonResponse
    {
        $payload = $request->validate(['name' => ['required', 'string'], 'permissions' => ['required', 'array']]);
        $role = Role::query()->firstOrCreate(['name' => $payload['name']]);
        $role = $this->permissionService->syncRolePermissions($role, $payload['permissions']);
        $this->log($request, 'admin.role.upsert', $payload);
        return response()->json(['data' => $role], 201);
    }

    private function resourceModel(string $resource): string
    {
        return match ($resource) {
            'nft' => Nft::class,
            'nft-sales' => NftSale::class,
            'agri-projects' => FarmingProject::class,
            'sports-athletes' => Athlete::class,
            'courses' => Course::class,
            'campaigns' => \App\Models\Campaign::class,
            'lottery' => LotteryGame::class,
            'lottery-winners' => LotteryResult::class,
            'giftcards' => Giftcard::class,
            'giftcard-orders' => GiftcardOrder::class,
            default => throw new RuntimeException('Unsupported admin resource.'),
        };
    }

    private function log(Request $request, string $action, array $data = []): void
    {
        $admin = $request->user();
        $this->audit->log($admin instanceof Admin ? $admin : null, $action, $data, $request);
    }
}
