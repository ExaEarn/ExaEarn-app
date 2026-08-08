<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Balance;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\FeeCalculator;
use App\Services\FiatWithdrawalService;
use App\Services\TransactionGuardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WithdrawalCenterController extends Controller
{
    private const SCALE = 8;

    public function meta(Request $request, FeeCalculator $fees): JsonResponse
    {
        $user = $request->user();
        $balances = Balance::query()->where('user_id', $user->id)->get()->keyBy(fn (Balance $balance) => strtoupper((string) $balance->asset));

        $assets = collect(config('wallet.assets', []))
            ->map(function (array $asset) use ($balances, $fees): array {
                $symbol = strtoupper((string) ($asset['code'] ?? ''));
                $balance = $balances->get($symbol);
                $withdrawable = (string) ($balance?->funding_available ?? '0');
                $networks = $this->withdrawNetworkCatalogForAsset($symbol, $fees)->values()->all();
                $enabled = $symbol !== '' && !in_array($symbol, ['NGN'], true) && !empty($networks);

                return [
                    'symbol' => $symbol,
                    'name' => $this->assetName($symbol),
                    'icon' => null,
                    'withdrawEnabled' => $enabled,
                    'internalTransferEnabled' => $enabled,
                    'availableBalance' => $withdrawable,
                    'withdrawableBalance' => $withdrawable,
                    'lockedBalance' => '0',
                    'fiatValue' => null,
                    'sourceAccounts' => [
                        ['id' => 'funding', 'name' => 'Funding', 'withdrawableBalance' => $withdrawable],
                    ],
                    'supportedNetworks' => $networks,
                ];
            })
            ->filter(fn (array $asset): bool => (bool) $asset['withdrawEnabled'])
            ->values()
            ->all();

        $supportedFiat = collect((array) config('swap.supported_fiat', ['NGN']))
            ->map(fn (string $currency): string => strtoupper($currency))
            ->values()
            ->all();
        $preferredFiat = $supportedFiat[0] ?? 'NGN';

        $historyPreview = Withdrawal::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Withdrawal $withdrawal): array => [
                'reference' => $withdrawal->transaction_id ?: ('WD-' . $withdrawal->id),
                'currency' => strtoupper((string) $withdrawal->currency),
                'amount' => (string) $withdrawal->amount,
                'status' => $this->mapWithdrawalStatus($withdrawal),
                'kind' => $this->withdrawalKind($withdrawal),
                'created_at' => optional($withdrawal->created_at)?->toISOString(),
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'methods' => [
                    [
                        'id' => 'crypto',
                        'title' => 'Crypto Withdrawal',
                        'description' => 'On-chain withdrawal or transfer to another ExaEarn user.',
                        'enabled' => true,
                    ],
                    [
                        'id' => 'sell_fiat',
                        'title' => 'Fiat Withdrawal',
                        'description' => 'Withdraw fiat from your ExaEarn funding balance to a supported bank or payout destination.',
                        'enabled' => !empty($supportedFiat),
                    ],
                    [
                        'id' => 'p2p',
                        'title' => 'P2P Trading',
                        'description' => 'Sell crypto to other users using supported payment methods.',
                        'enabled' => true,
                    ],
                ],
                'preferred_fiat_currency' => $preferredFiat,
                'fiat_currencies' => $supportedFiat,
                'assets' => $assets,
                'recent_assets' => collect($historyPreview)->pluck('currency')->filter()->unique()->values()->all(),
                'history_preview' => $historyPreview,
                'recipient_types' => [
                    ['id' => 'email', 'label' => 'Email', 'enabled' => true],
                    ['id' => 'exaearn_id', 'label' => 'ExaEarn ID', 'enabled' => true],
                    ['id' => 'username', 'label' => 'Username', 'enabled' => true],
                ],
                'limits' => $this->withdrawLimits(),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $filter = strtolower((string) $request->query('type', 'all'));

        $items = Withdrawal::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->get()
            ->filter(function (Withdrawal $withdrawal) use ($filter): bool {
                if ($filter === 'all') {
                    return true;
                }

                return $this->withdrawalKind($withdrawal) === $filter;
            })
            ->take(50)
            ->map(function (Withdrawal $withdrawal): array {
                $metadata = is_array($withdrawal->metadata) ? $withdrawal->metadata : [];

                return [
                    'reference' => $withdrawal->transaction_id ?: ('WD-' . $withdrawal->id),
                    'currency' => strtoupper((string) $withdrawal->currency),
                    'amount' => (string) $withdrawal->amount,
                    'fee' => (string) $withdrawal->fee,
                    'network' => $withdrawal->network,
                    'address' => $withdrawal->address,
                    'tx_hash' => $withdrawal->tx_hash,
                    'kind' => $this->withdrawalKind($withdrawal),
                    'status' => $this->mapWithdrawalStatus($withdrawal),
                    'recipient' => $metadata['recipient'] ?? null,
                    'created_at' => optional($withdrawal->created_at)?->toISOString(),
                    'confirmed_at' => optional($withdrawal->confirmed_at)?->toISOString(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => ['items' => $items],
        ]);
    }

    public function preview(Request $request, FeeCalculator $fees): JsonResponse
    {
        $payload = $request->validate([
            'flow' => ['required', 'string', 'in:on_chain,internal'],
            'currency' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'network' => ['nullable', 'string', 'max:32'],
        ]);

        $currency = strtoupper((string) $payload['currency']);
        $amount = (string) $payload['amount'];
        $flow = (string) $payload['flow'];
        $balance = Balance::query()->where('user_id', $request->user()->id)->where('asset', $currency)->first();
        $withdrawableBalance = (string) ($balance?->funding_available ?? '0');

        if ($this->compare($amount, $withdrawableBalance) === 1) {
            return response()->json(['message' => 'Insufficient available balance.'], 422);
        }

        if ($flow === 'internal') {
            return response()->json([
                'success' => true,
                'status' => 'success',
                'data' => [
                    'currency' => $currency,
                    'amount' => $amount,
                    'fee' => '0',
                    'amount_received' => $amount,
                    'withdrawable_balance' => $withdrawableBalance,
                    'source_account' => 'funding',
                    'limits' => $this->withdrawLimits(),
                ],
            ]);
        }

        $quote = $fees->withdrawal($amount, $currency);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'currency' => $currency,
                'amount' => $quote['gross_amount'],
                'fee' => $quote['fee_amount'],
                'amount_received' => $quote['net_amount'],
                'withdrawable_balance' => $withdrawableBalance,
                'source_account' => 'funding',
                'limits' => $this->withdrawLimits(),
            ],
        ]);
    }

    public function internalLookup(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'identifier_type' => ['required', 'string', 'in:email,exaearn_id,username'],
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $identifier = trim((string) $payload['identifier']);
        $user = match ((string) $payload['identifier_type']) {
            'email' => User::query()->where('email', strtolower($identifier))->first(),
            'exaearn_id' => User::query()->where('unique_user_id', $identifier)->first(),
            'username' => User::query()->where('name', $identifier)->first(),
        };

        if (!$user) {
            return response()->json(['message' => 'We could not find a matching ExaEarn recipient.'], 404);
        }

        if ((int) $user->id === (int) $request->user()->id) {
            return response()->json(['message' => 'You cannot transfer funds to the same account.'], 422);
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'display_name' => $user->name,
                'exaearn_id' => $user->unique_user_id ?: ('EXA-' . str_pad((string) $user->id, 8, '0', STR_PAD_LEFT)),
                'masked_email' => $this->maskEmail((string) $user->email),
            ],
        ]);
    }

    public function internalTransfer(Request $request, TransactionGuardService $guard): JsonResponse
    {
        $payload = $request->validate([
            'currency' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'identifier_type' => ['required', 'string', 'in:email,exaearn_id,username'],
            'identifier' => ['required', 'string', 'max:255'],
            'two_factor_code' => ['nullable', 'string', 'max:12'],
        ]);

        $sender = $request->user();
        $currency = strtoupper((string) $payload['currency']);
        $amount = (string) $payload['amount'];

        $guard->guardWithdrawal($sender, $amount);

        $recipient = match ((string) $payload['identifier_type']) {
            'email' => User::query()->where('email', strtolower(trim((string) $payload['identifier'])))->first(),
            'exaearn_id' => User::query()->where('unique_user_id', trim((string) $payload['identifier']))->first(),
            'username' => User::query()->where('name', trim((string) $payload['identifier']))->first(),
        };

        if (!$recipient) {
            return response()->json(['message' => 'We could not find a matching ExaEarn recipient.'], 404);
        }

        if ((int) $recipient->id === (int) $sender->id) {
            return response()->json(['message' => 'You cannot transfer funds to the same account.'], 422);
        }

        $reference = 'IWT-' . strtoupper(Str::random(12));

        try {
            DB::transaction(function () use ($sender, $recipient, $currency, $amount, $reference): void {
                $senderBalance = Balance::query()->lockForUpdate()->firstOrCreate(
                    ['user_id' => $sender->id, 'asset' => $currency],
                    ['funding_available' => '0', 'spot_available' => '0', 'spot_locked' => '0', 'futures_available' => '0', 'futures_margin' => '0']
                );
                $recipientBalance = Balance::query()->lockForUpdate()->firstOrCreate(
                    ['user_id' => $recipient->id, 'asset' => $currency],
                    ['funding_available' => '0', 'spot_available' => '0', 'spot_locked' => '0', 'futures_available' => '0', 'futures_margin' => '0']
                );

                if ($this->compare((string) $senderBalance->funding_available, $amount) === -1) {
                    throw new RuntimeException('Insufficient available balance.');
                }

                $senderBalance->funding_available = $this->sub((string) $senderBalance->funding_available, $amount);
                $recipientBalance->funding_available = $this->add((string) $recipientBalance->funding_available, $amount);

                $senderBalance->save();
                $recipientBalance->save();

                Withdrawal::query()->create([
                    'user_id' => $sender->id,
                    'transaction_id' => $reference,
                    'currency' => $currency,
                    'amount' => $amount,
                    'fee' => '0',
                    'address' => null,
                    'network' => null,
                    'tx_hash' => null,
                    'status' => 'completed',
                    'confirmed_at' => now(),
                    'metadata' => [
                        'kind' => 'internal',
                        'recipient' => [
                            'id' => $recipient->id,
                            'name' => $recipient->name,
                            'exaearn_id' => $recipient->unique_user_id,
                        ],
                    ],
                ]);
            });
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Internal transfer completed successfully.',
            'data' => [
                'reference' => $reference,
                'status' => 'completed',
            ],
        ]);
    }

    public function onChain(Request $request, FeeCalculator $fees, TransactionGuardService $guard): JsonResponse
    {
        $payload = $request->validate([
            'currency' => ['required', 'string', 'max:16'],
            'network' => ['required', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'memo' => ['nullable', 'string', 'max:255'],
            'two_factor_code' => ['nullable', 'string', 'max:12'],
        ]);

        $user = $request->user();
        $currency = strtoupper((string) $payload['currency']);
        $network = strtolower((string) $payload['network']);
        $amount = (string) $payload['amount'];
        $guard->guardWithdrawal($user, $amount);

        $quote = $fees->withdrawal($amount, $currency);
        $reference = 'WD-' . strtoupper(Str::random(12));

        try {
            DB::transaction(function () use ($user, $currency, $amount, $network, $payload, $reference, $quote): void {
                $balance = Balance::query()->lockForUpdate()->firstOrCreate(
                    ['user_id' => $user->id, 'asset' => $currency],
                    ['funding_available' => '0', 'spot_available' => '0', 'spot_locked' => '0', 'futures_available' => '0', 'futures_margin' => '0']
                );

                if ($this->compare((string) $balance->funding_available, $amount) === -1) {
                    throw new RuntimeException('Insufficient available balance.');
                }

                $balance->funding_available = $this->sub((string) $balance->funding_available, $amount);
                $balance->save();

                Withdrawal::query()->create([
                    'user_id' => $user->id,
                    'transaction_id' => $reference,
                    'currency' => $currency,
                    'amount' => $quote['gross_amount'],
                    'fee' => $quote['fee_amount'],
                    'address' => (string) $payload['address'],
                    'network' => $network,
                    'tx_hash' => null,
                    'status' => 'pending',
                    'confirmed_at' => null,
                    'metadata' => [
                        'kind' => 'on_chain',
                        'memo' => $payload['memo'] ?? null,
                        'amount_received' => $quote['net_amount'],
                    ],
                ]);
            });
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Withdrawal request submitted.',
            'data' => [
                'reference' => $reference,
                'status' => 'pending',
                'amount_received' => $quote['net_amount'],
            ],
        ], 201);
    }

    public function fiatBanks(FiatWithdrawalService $fiatWithdrawalService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'items' => $fiatWithdrawalService->getSupportedBanks(),
            ],
        ]);
    }

    private function withdrawNetworkCatalogForAsset(string $currency, FeeCalculator $fees)
    {
        $definitions = [
            'BTC' => [['id' => 'bitcoin', 'name' => 'Bitcoin', 'standard' => 'BTC', 'minimumWithdrawal' => '0.0001', 'estimatedArrival' => '3 confirmations', 'memoRequired' => false, 'status' => 'available']],
            'ETH' => [['id' => 'ethereum', 'name' => 'Ethereum', 'standard' => 'ERC20', 'minimumWithdrawal' => '0.001', 'estimatedArrival' => '12 confirmations', 'memoRequired' => false, 'status' => 'available']],
            'BNB' => [['id' => 'bsc', 'name' => 'BNB Smart Chain', 'standard' => 'BEP20', 'minimumWithdrawal' => '0.01', 'estimatedArrival' => '15 confirmations', 'memoRequired' => false, 'status' => 'available']],
            'MATIC' => [['id' => 'polygon', 'name' => 'Polygon', 'standard' => 'POL', 'minimumWithdrawal' => '1', 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available']],
            'XRP' => [['id' => 'xrpl', 'name' => 'XRP Ledger', 'standard' => 'XRP', 'minimumWithdrawal' => '10', 'estimatedArrival' => '1 confirmation', 'memoRequired' => true, 'status' => 'available']],
            'TRX' => [['id' => 'tron', 'name' => 'TRON', 'standard' => 'TRC20', 'minimumWithdrawal' => '1', 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available']],
            'SOL' => [['id' => 'solana', 'name' => 'Solana', 'standard' => 'SOL', 'minimumWithdrawal' => '0.01', 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available']],
            'TON' => [['id' => 'ton', 'name' => 'TON', 'standard' => 'TON', 'minimumWithdrawal' => '0.01', 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available']],
            'USDT' => [
                ['id' => 'tron', 'name' => 'TRON', 'standard' => 'TRC20', 'minimumWithdrawal' => '1', 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available'],
                ['id' => 'ethereum', 'name' => 'Ethereum', 'standard' => 'ERC20', 'minimumWithdrawal' => '5', 'estimatedArrival' => '12 confirmations', 'memoRequired' => false, 'status' => 'available'],
                ['id' => 'bsc', 'name' => 'BNB Smart Chain', 'standard' => 'BEP20', 'minimumWithdrawal' => '1', 'estimatedArrival' => '15 confirmations', 'memoRequired' => false, 'status' => 'available'],
                ['id' => 'solana', 'name' => 'Solana', 'standard' => 'SPL', 'minimumWithdrawal' => '1', 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available'],
                ['id' => 'polygon', 'name' => 'Polygon', 'standard' => 'POL', 'minimumWithdrawal' => '1', 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available'],
            ],
            'USDC' => [
                ['id' => 'ethereum', 'name' => 'Ethereum', 'standard' => 'ERC20', 'minimumWithdrawal' => '5', 'estimatedArrival' => '12 confirmations', 'memoRequired' => false, 'status' => 'available'],
                ['id' => 'base', 'name' => 'Base', 'standard' => 'Base', 'minimumWithdrawal' => '1', 'estimatedArrival' => '12 confirmations', 'memoRequired' => false, 'status' => 'available'],
                ['id' => 'solana', 'name' => 'Solana', 'standard' => 'SPL', 'minimumWithdrawal' => '1', 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available'],
            ],
            'EXA' => [['id' => 'base', 'name' => 'Base', 'standard' => 'EXA', 'minimumWithdrawal' => '10', 'estimatedArrival' => '12 confirmations', 'memoRequired' => false, 'status' => 'available']],
        ];

        $quote = null;
        try {
            $quote = $fees->withdrawal('100', $currency);
        } catch (\Throwable) {
            $quote = ['fee_amount' => '0'];
        }

        return collect($definitions[$currency] ?? [])->map(function (array $network) use ($quote): array {
            $network['fee'] = (string) ($quote['fee_amount'] ?? '0');
            return $network;
        });
    }

    private function assetName(string $currency): string
    {
        return [
            'BTC' => 'Bitcoin',
            'ETH' => 'Ethereum',
            'USDT' => 'Tether USD',
            'USDC' => 'USD Coin',
            'BNB' => 'BNB',
            'MATIC' => 'Polygon',
            'XRP' => 'XRP',
            'TRX' => 'TRON',
            'SOL' => 'Solana',
            'TON' => 'Toncoin',
            'EXA' => 'ExaToken',
        ][$currency] ?? $currency;
    }

    private function withdrawLimits(): array
    {
        return [
            'daily_limit' => (string) config('wallet.withdrawals.daily_limit', '25000'),
            'max_per_request' => (string) config('wallet.withdrawals.max_per_request', '10000'),
        ];
    }

    private function mapWithdrawalStatus(Withdrawal $withdrawal): array
    {
        $raw = $withdrawal->status?->value ?? (string) $withdrawal->status;

        return match ($raw) {
            'pending' => ['key' => 'pending_review', 'label' => 'Pending Review'],
            'processing' => ['key' => 'processing', 'label' => 'Processing'],
            'completed' => ['key' => 'completed', 'label' => 'Completed'],
            'failed' => ['key' => 'failed', 'label' => 'Failed'],
            'cancelled' => ['key' => 'cancelled', 'label' => 'Cancelled'],
            default => ['key' => 'processing', 'label' => 'Processing'],
        };
    }

    private function withdrawalKind(Withdrawal $withdrawal): string
    {
        $metadata = is_array($withdrawal->metadata) ? $withdrawal->metadata : [];

        if (($metadata['kind'] ?? null) === 'internal') {
            return 'internal';
        }

        if (($metadata['destination_type'] ?? null) === 'bank' || $withdrawal->network === null) {
            return 'fiat';
        }

        return 'on_chain';
    }

    private function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $prefix = substr($local, 0, 2);
        return $prefix . str_repeat('*', max(strlen($local) - 2, 2)) . '@' . $domain;
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, self::SCALE) : number_format((float) $a + (float) $b, self::SCALE, '.', '');
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, self::SCALE) : number_format((float) $a - (float) $b, self::SCALE, '.', '');
    }

    private function compare(string $a, string $b): int
    {
        return function_exists('bccomp') ? bccomp($a, $b, self::SCALE) : ((float) $a <=> (float) $b);
    }
}
