<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Enums\TransactionStatus;
use App\Models\FiatDepositIntent;
use App\Models\Balance;
use App\Models\DepositAddress;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\BlockchainService;
use App\Services\FeeCalculator;
use App\Services\FeeTreasuryService;
use App\Services\PaymentGatewayService;
use App\Services\TransferService;
use App\Services\VirtualAccountService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WalletController extends Controller
{
    public function balances(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $configured = collect(config('wallet.assets', []))
            ->map(function (array $asset): array {
                $code = strtoupper((string) ($asset['code'] ?? ''));

                return [
                    'currency' => $code,
                    'network' => (string) ($asset['network'] ?? ''),
                    'type' => (string) ($asset['type'] ?? 'unknown'),
                    'decimals' => (int) ($asset['decimals'] ?? 8),
                ];
            })
            ->filter(fn (array $asset): bool => $asset['currency'] !== '')
            ->keyBy('currency');

        $wallets = Wallet::query()
            ->where('user_id', $userId)
            ->orderBy('currency')
            ->get()
            ->keyBy(fn (Wallet $wallet): string => strtoupper((string) $wallet->currency));

        $supportedFiat = collect((array) config('swap.supported_fiat', []))
            ->map(fn (string $code): array => [
                'currency' => strtoupper($code),
                'network' => 'fiat',
                'type' => 'fiat',
                'decimals' => 2,
            ])
            ->keyBy('currency');

        $catalog = $configured->union($supportedFiat);

        $rows = $catalog->map(function (array $asset, string $currency) use ($wallets): array {
            /** @var Wallet|null $wallet */
            $wallet = $wallets->get($currency);

            return [
                'currency' => $currency,
                'balance' => (string) ($wallet?->available_balance ?? '0'),
                'locked' => (string) ($wallet?->locked_balance ?? '0'),
                'total' => (string) ($wallet?->total_balance ?? '0'),
                'pending' => (string) ($wallet?->pending_balance ?? '0'),
                'network' => $asset['network'],
                'type' => $asset['type'],
                'decimals' => $asset['decimals'],
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $rows,
        ]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $balances = Balance::query()->where('user_id', $userId)->get();

        $summary = [
            'funding' => ['available' => '0', 'locked' => '0', 'total' => '0'],
            'spot' => ['available' => '0', 'locked' => '0', 'total' => '0'],
            'futures' => ['available' => '0', 'locked' => '0', 'total' => '0'],
        ];

        $assets = [];
        foreach ($balances as $balance) {
            $asset = strtoupper((string) $balance->asset);
            $fundingAvailable = (string) ($balance->funding_available ?? '0');
            $spotAvailable = (string) ($balance->spot_available ?? '0');
            $spotLocked = (string) ($balance->spot_locked ?? '0');
            $futuresAvailable = (string) ($balance->futures_available ?? '0');
            $futuresMargin = (string) ($balance->futures_margin ?? '0');

            $summary['funding']['available'] = $this->addDecimal($summary['funding']['available'], $fundingAvailable);
            $summary['funding']['total'] = $this->addDecimal($summary['funding']['total'], $fundingAvailable);

            $summary['spot']['available'] = $this->addDecimal($summary['spot']['available'], $spotAvailable);
            $summary['spot']['locked'] = $this->addDecimal($summary['spot']['locked'], $spotLocked);
            $summary['spot']['total'] = $this->addDecimal($summary['spot']['total'], $this->addDecimal($spotAvailable, $spotLocked));

            $summary['futures']['available'] = $this->addDecimal($summary['futures']['available'], $futuresAvailable);
            $summary['futures']['locked'] = $this->addDecimal($summary['futures']['locked'], $futuresMargin);
            $summary['futures']['total'] = $this->addDecimal($summary['futures']['total'], $this->addDecimal($futuresAvailable, $futuresMargin));

            $assets[] = [
                'asset' => $asset,
                'funding' => ['available' => $fundingAvailable, 'locked' => '0', 'total' => $fundingAvailable],
                'spot' => ['available' => $spotAvailable, 'locked' => $spotLocked, 'total' => $this->addDecimal($spotAvailable, $spotLocked)],
                'futures' => ['available' => $futuresAvailable, 'locked' => $futuresMargin, 'total' => $this->addDecimal($futuresAvailable, $futuresMargin)],
            ];
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'accounts' => $summary,
                'assets' => $assets,
            ],
        ]);
    }

    public function depositMeta(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $assetCodes = collect(config('wallet.assets', []))
            ->map(fn (array $asset): string => strtoupper((string) ($asset['code'] ?? '')))
            ->filter()
            ->unique()
            ->values();
        $walletsByCurrency = Wallet::query()
            ->where('user_id', $userId)
            ->whereIn('currency', $assetCodes)
            ->get()
            ->keyBy(fn (Wallet $wallet): string => strtoupper((string) $wallet->currency));
        $activeAddressCurrencies = DepositAddress::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereIn('currency', $assetCodes)
            ->pluck('currency')
            ->map(fn ($currency): string => strtoupper((string) $currency))
            ->flip();

        $assets = collect(config('wallet.assets', []))
            ->map(fn (array $asset): array => $this->buildDepositAsset($asset, $walletsByCurrency, $activeAddressCurrencies))
            ->filter(fn (array $asset): bool => (bool) $asset['depositEnabled'])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'funding_methods' => [
                    ['id' => 'deposit-crypto', 'title' => 'Deposit Crypto', 'description' => 'Transfer crypto from your wallet or another exchange.', 'enabled' => true],
                    ['id' => 'exa-pay', 'title' => 'Receive via ExaEarn Pay', 'description' => 'Receive instantly from another ExaEarn user.', 'enabled' => true],
                    ['id' => 'deposit-fiat', 'title' => 'Deposit Fiat', 'description' => 'Fund through available bank, card, or payment rails.', 'enabled' => true],
                    ['id' => 'p2p', 'title' => 'P2P Marketplace', 'description' => 'Buy crypto from verified users using supported local payments.', 'enabled' => true],
                ],
                'assets' => $assets,
                'recommended_assets' => ['BTC', 'ETH', 'USDT', 'USDC', 'SOL', 'XRP', 'TRX', 'BNB', 'EXA'],
                'recent_assets' => collect(
                    Transaction::query()
                        ->where('user_id', $user->id)
                        ->where('type', TransactionType::Deposit)
                        ->latest('id')
                        ->limit(6)
                        ->pluck('currency')
                )->map(fn ($currency) => strtoupper((string) $currency))->unique()->values()->all(),
                'route_destination' => 'Funding',
                'fiat_methods' => $this->fiatMethodCatalog()->values()->all(),
                'receive' => [
                    'username' => $user->name,
                    'exaearn_id' => $user->unique_user_id ?: ('EXA-' . str_pad((string) $user->id, 8, '0', STR_PAD_LEFT)),
                    'deep_link' => 'exaearn://pay?recipient=' . rawurlencode((string) ($user->unique_user_id ?: $user->id)),
                    'share_link' => 'https://exaearn.com/pay/' . rawurlencode((string) ($user->unique_user_id ?: $user->id)),
                ],
            ],
        ]);
    }

    public function depositAddress(Request $request, BlockchainService $blockchain): JsonResponse
    {
        $payload = $request->validate([
            'currency' => ['required', 'string', 'max:16'],
            'network' => ['required', 'string', 'max:32'],
        ]);

        $currency = strtoupper((string) $payload['currency']);
        $network = strtolower((string) $payload['network']);
        $user = $request->user();

        $address = DepositAddress::query()
            ->where('user_id', $user->id)
            ->where('currency', $currency)
            ->where('network', $network)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (!$address) {
            try {
                $generated = $blockchain->generateDepositAddress((int) $user->id, $currency, $network);
            } catch (RuntimeException $exception) {
                report($exception);

                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Deposit address generation is temporarily unavailable. Please try again shortly.',
                ], 503);
            }

            $address = DepositAddress::query()->create([
                'user_id' => $user->id,
                'currency' => $currency,
                'address' => (string) ($generated['address'] ?? $generated['data']['address'] ?? ''),
                'network' => $network,
                'address_type' => (string) ($generated['address_type'] ?? 'deposit'),
                'derivation_path' => $generated['derivation_path'] ?? null,
                'address_index' => $generated['address_index'] ?? null,
                'status' => 'active',
                'metadata' => $generated['metadata'] ?? [],
            ]);
        }

        $networkDetails = $this->networkCatalogForAsset($currency)->firstWhere('id', $network);
        $metadata = is_array($address->metadata) ? $address->metadata : [];
        $memo = $metadata['destination_tag'] ?? $metadata['memo'] ?? (($networkDetails['memoRequired'] ?? false) ? (string) ($user->id + 100000) : null);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'currency' => $currency,
                'network' => $networkDetails ?: ['id' => $network, 'name' => strtoupper($network)],
                'address' => $address->address,
                'memo' => $memo,
                'memo_label' => $network === 'xrpl' ? 'Destination Tag' : 'Memo',
                'route_to' => 'Funding',
                'network_warning' => sprintf('Only deposit %s using %s. Deposits sent via unsupported networks may not be recoverable.', $currency, $networkDetails['name'] ?? strtoupper($network)),
                'status' => 'waiting',
            ],
        ]);
    }

    public function depositHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $statusFilter = strtolower((string) $request->query('status', 'all'));

        $query = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::Deposit)
            ->latest('id');

        if ($statusFilter !== 'all') {
            $statusMap = [
                'pending' => ['pending', 'processing'],
                'completed' => ['completed'],
                'failed' => ['failed', 'cancelled'],
            ];
            $query->whereIn('status', $statusMap[$statusFilter] ?? ['pending', 'processing']);
        }

        $items = $query
            ->limit(100)
            ->get()
            ->reject(function (Transaction $transaction): bool {
                $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];

                return ($metadata['source'] ?? null) === 'fiat_intent_settlement';
            })
            ->take(50)
            ->map(function (Transaction $transaction): array {
                $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
                $rawStatus = $transaction->status?->value ?? (string) $transaction->status;

                [$statusKey, $statusLabel] = match ($rawStatus) {
                    'pending' => ['waiting', 'Waiting for Deposit'],
                    'processing' => ['confirming', 'Confirming'],
                    'completed' => ['credited', 'Credited'],
                    'failed', 'cancelled' => ['failed', 'Failed'],
                    default => ['review', 'Needs Review'],
                };

                return [
                    'id' => $transaction->transaction_id,
                    'currency' => strtoupper((string) $transaction->currency),
                    'amount' => (string) $transaction->amount,
                    'network' => strtoupper((string) ($metadata['network'] ?? $metadata['chain'] ?? 'deposit')),
                    'status_key' => $statusKey,
                    'status_label' => $statusLabel,
                    'status_code' => $rawStatus,
                    'tx_hash' => $transaction->tx_hash,
                    'reference' => $transaction->reference,
                    'transaction_id' => $transaction->transaction_id,
                    'created_at' => optional($transaction->created_at)?->toISOString(),
                    'confirmations' => (int) ($metadata['confirmations'] ?? 0),
                    'required_confirmations' => (int) ($metadata['required_confirmations'] ?? 0),
                    'explorer_url' => null,
                    'source' => 'blockchain',
                ];
            })
            ->values()
            ->all();

        $intentItems = FiatDepositIntent::query()
            ->where('user_id', $user->id)
            ->when($statusFilter !== 'all', function ($intentQuery) use ($statusFilter) {
                return match ($statusFilter) {
                    'pending' => $intentQuery->whereIn('status', ['pending', 'submitted', 'paid', 'expired']),
                    'completed' => $intentQuery->where('status', 'credited'),
                    'failed' => $intentQuery->whereIn('status', ['cancelled', 'failed']),
                    default => $intentQuery,
                };
            })
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (FiatDepositIntent $intent): array {
                [$statusKey, $statusLabel] = match ((string) $intent->status) {
                    'pending', 'submitted' => ['waiting', 'Awaiting Payment'],
                    'paid' => ['confirming', 'Payment Under Review'],
                    'credited' => ['credited', 'Credited'],
                    'failed', 'cancelled' => ['failed', 'Failed'],
                    'expired' => ['review', 'Expired'],
                    default => ['review', 'Needs Review'],
                };

                return [
                    'id' => $intent->reference,
                    'currency' => strtoupper((string) $intent->currency),
                    'amount' => (string) $intent->gross_amount,
                    'network' => strtoupper((string) ($intent->method_id ?: 'fiat')),
                    'status_key' => $statusKey,
                    'status_label' => $statusLabel,
                    'status_code' => (string) $intent->status,
                    'tx_hash' => null,
                    'reference' => $intent->reference,
                    'transaction_id' => null,
                    'created_at' => optional($intent->created_at)?->toISOString(),
                    'confirmations' => 0,
                    'required_confirmations' => 0,
                    'explorer_url' => null,
                    'source' => 'fiat_intent',
                    'fee_amount' => (string) $intent->fee_amount,
                    'net_amount' => (string) $intent->net_amount,
                    'expires_at' => optional($intent->expires_at)?->toISOString(),
                ];
            })
            ->values()
            ->all();

        $merged = collect(array_merge($items, $intentItems))
            ->sortByDesc(fn (array $item) => strtotime((string) ($item['created_at'] ?? '1970-01-01T00:00:00Z')))
            ->take(50)
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'items' => $merged,
            ],
        ]);
    }

    public function fiatDepositInstructions(Request $request, FeeCalculator $fees, PaymentGatewayService $paymentGatewayService, VirtualAccountService $virtualAccountService): JsonResponse
    {
        $payload = $request->validate([
            'method_id' => ['required', 'string', 'max:64'],
            'currency' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $user = $request->user();
        $methodId = strtolower((string) $payload['method_id']);
        $currency = strtoupper((string) $payload['currency']);
        $amount = (string) $payload['amount'];

        $method = $this->fiatMethodCatalog()->firstWhere('id', $methodId);
        if (!$method || !($method['enabled'] ?? false)) {
            return response()->json(['message' => 'This fiat funding method is not currently available.'], 422);
        }

        if (!in_array($currency, (array) ($method['currencies'] ?? []), true)) {
            return response()->json(['message' => 'This settlement currency is not enabled for the selected fiat method.'], 422);
        }

        $quote = $fees->fiatDeposit($amount, $currency);
        $reference = sprintf('FDP-%d-%s', (int) $user->id, strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)));
        $windowMinutes = (int) ($method['payment_window_minutes'] ?? 30);
        $createdAt = now();

        $accountTag = $user->unique_user_id ?: ('EXA-' . str_pad((string) $user->id, 8, '0', STR_PAD_LEFT));
        $instructions = $this->buildFiatInstructions($methodId, $currency, $reference, $accountTag);
        if ($methodId === 'card_payment') {
            $checkout = $paymentGatewayService->createHostedCheckoutIntent(
                (int) $user->id,
                'flutterwave',
                $currency,
                $quote['gross_amount'],
                $reference,
                [
                    'email' => (string) ($user->email ?? ''),
                    'name' => (string) ($user->name ?? 'ExaEarn User'),
                    'phone' => (string) ($user->phone ?? ''),
                ],
                $this->fiatRedirectUrl($reference),
                ['card'],
                [
                    'source' => 'fiat_card_deposit',
                    'fiat_intent_reference' => $reference,
                ]
            );

            $instructions = array_merge($instructions, [
                'provider' => $checkout['provider'],
                'checkout_url' => $checkout['checkout_url'],
                'redirect_url' => $checkout['redirect_url'],
                'action_label' => 'Continue to secure card checkout',
            ]);
        } elseif ($methodId === 'payment_gateway') {
            $virtualAccount = $virtualAccountService->create($user, $currency);
            if (!$virtualAccount) {
                return response()->json(['message' => 'Payment gateway collections are not available right now.'], 422);
            }

            $instructions = $this->buildFiatInstructions($methodId, $currency, $reference, $accountTag, [
                'virtual_account' => [
                    'bank_name' => $virtualAccount->bank_name,
                    'account_name' => $virtualAccount->account_name,
                    'account_number' => $virtualAccount->account_number,
                    'provider' => $virtualAccount->provider,
                ],
            ]);
        }

        $disclosures = [
            'Funds are credited after ExaEarn matches the incoming fiat payment to the provided reference.',
            'Use the exact settlement currency and reference shown here to avoid delays.',
            'Large or unusual fiat deposits may require additional compliance review before crediting.',
        ];
        $expiresAt = $createdAt->copy()->addMinutes($windowMinutes);

        FiatDepositIntent::query()->create([
            'reference' => $reference,
            'user_id' => (int) $user->id,
            'method_id' => $methodId,
            'currency' => $currency,
            'gross_amount' => $quote['gross_amount'],
            'fee_amount' => $quote['fee_amount'],
            'net_amount' => $quote['net_amount'],
            'rate_bps' => $quote['rate_bps'],
            'fixed_fee' => $quote['fixed_fee'],
            'route_destination' => 'Funding',
            'status' => 'pending',
            'instructions' => $instructions,
            'disclosures' => $disclosures,
            'metadata' => [
                'user_unique_id' => $user->unique_user_id,
                'payment_window_minutes' => $windowMinutes,
            ],
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'method' => $method,
                'currency' => $currency,
                'amount' => $quote['gross_amount'],
                'fee_amount' => $quote['fee_amount'],
                'net_amount' => $quote['net_amount'],
                'rate_bps' => $quote['rate_bps'],
                'fixed_fee' => $quote['fixed_fee'],
                'reference' => $reference,
                'route_destination' => 'Funding',
                'created_at' => $createdAt->toISOString(),
                'expires_at' => $expiresAt->toISOString(),
                'payment_window_minutes' => $windowMinutes,
                'instructions' => $instructions,
                'disclosures' => $disclosures,
            ],
        ]);
    }

    public function markFiatDepositIntentPaid(Request $request, string $reference): JsonResponse
    {
        $user = $request->user();

        $intent = FiatDepositIntent::query()
            ->where('user_id', $user->id)
            ->where('reference', $reference)
            ->firstOrFail();

        if (in_array((string) $intent->status, ['credited', 'cancelled', 'failed'], true)) {
            return response()->json(['message' => 'This fiat deposit intent can no longer be updated.'], 422);
        }

        if ((string) $intent->status === 'paid') {
            return response()->json([
                'success' => true,
                'status' => 'success',
                'data' => ['reference' => $intent->reference, 'intent_status' => (string) $intent->status],
            ]);
        }

        $intent->status = 'paid';
        $intent->paid_at = now();
        $intent->save();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Fiat payment marked for review.',
            'data' => ['reference' => $intent->reference, 'intent_status' => (string) $intent->status],
        ]);
    }

    public function settleFiatDepositIntent(Request $request, string $reference, FeeTreasuryService $fees): JsonResponse
    {
        abort_unless((string) ($request->user()->role ?? '') === 'admin', 403, 'Administrator access required.');

        $intent = FiatDepositIntent::query()->where('reference', $reference)->lockForUpdate()->firstOrFail();

        if ((string) $intent->status === 'credited') {
            return response()->json([
                'success' => true,
                'status' => 'success',
                'data' => ['reference' => $intent->reference, 'intent_status' => 'credited'],
            ]);
        }

        if (in_array((string) $intent->status, ['cancelled', 'failed', 'expired'], true)) {
            return response()->json(['message' => 'This fiat deposit intent cannot be settled in its current state.'], 422);
        }

        $result = $fees->collectFiatDeposit(
            (int) $intent->user_id,
            (string) $intent->gross_amount,
            (string) $intent->currency,
            (string) $intent->reference,
            [
                'source' => 'fiat_intent_settlement',
                'fiat_intent_reference' => $intent->reference,
                'method_id' => $intent->method_id,
            ]
        );

        Transaction::query()->updateOrCreate(
            ['reference' => $intent->reference],
            [
                'transaction_id' => $intent->reference,
                'user_id' => (int) $intent->user_id,
                'type' => TransactionType::Deposit,
                'currency' => (string) $intent->currency,
                'amount' => (string) $intent->net_amount,
                'fee' => (string) $intent->fee_amount,
                'status' => TransactionStatus::Completed,
                'metadata' => [
                    'source' => 'fiat_intent_settlement',
                    'fiat_intent_reference' => $intent->reference,
                    'gross_amount' => (string) $intent->gross_amount,
                    'fee_amount' => (string) $intent->fee_amount,
                    'method_id' => (string) $intent->method_id,
                ],
            ]
        );

        $intent->status = 'credited';
        $intent->settled_at = now();
        $intent->save();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Fiat deposit intent settled and credited.',
            'data' => [
                'reference' => $intent->reference,
                'intent_status' => (string) $intent->status,
                'ledger_reference' => $result['ledger_transaction']->reference,
                'net_amount' => $result['net_deposit'],
            ],
        ]);
    }
    public function transfer(Request $request, WalletService $service)
    {
        $validated = $request->validate([
            'asset' => 'required|string',
            'from' => 'required|in:funding,spot,futures',
            'to' => 'required|in:funding,spot,futures',
            'amount' => 'required|numeric|min:0.00000001',
        ]);

        return $service->transfer(
            auth()->id(),
            $validated['asset'],
            $validated['from'],
            $validated['to'],
            $validated['amount'],
            uniqid('txn_')
        );
    }

    public function internalTransfer(Request $request, TransferService $service)
    {
        $validated = $request->validate([
            'from_wallet' => 'required|in:funding,spot,futures',
            'to_wallet' => 'required|in:funding,spot,futures',
            'asset' => 'required|string',
            'amount' => 'required|numeric|min:0.00000001',
        ]);

        try {
            $service->internalTransfer(
                (int) auth()->id(),
                $validated['from_wallet'],
                $validated['to_wallet'],
                strtoupper($validated['asset']),
                (string) $validated['amount']
            );
        } catch (\InvalidArgumentException|RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Internal transfer completed.']);
    }

    private function buildDepositAsset(array $asset, $walletsByCurrency, $activeAddressCurrencies): array
    {
        $currency = strtoupper((string) ($asset['code'] ?? ''));
        $networks = $this->networkCatalogForAsset($currency)->values()->all();
        $wallet = $walletsByCurrency->get($currency);
        $hasAddress = $activeAddressCurrencies->has($currency);

        return [
            'symbol' => $currency,
            'name' => $this->assetName($currency),
            'icon' => null,
            'category' => ($asset['type'] ?? 'crypto') === 'fiat' ? 'fiat' : 'crypto',
            'depositEnabled' => !in_array($currency, ['NGN', 'USD'], true),
            'availableBalance' => (string) ($wallet?->available_balance ?? '0'),
            'balance' => (string) ($wallet?->available_balance ?? '0'),
            'lockedBalance' => (string) ($wallet?->locked_balance ?? '0'),
            'networks' => $networks,
            'minimumDeposit' => $networks[0]['minimumDeposit'] ?? '0',
            'confirmations' => $networks[0]['depositConfirmations'] ?? 0,
            'hasAddress' => $hasAddress,
        ];
    }

    private function fiatMethodCatalog()
    {
        $supportedFiat = collect((array) config('swap.supported_fiat', []))
            ->map(fn (string $code): string => strtoupper($code))
            ->values()
            ->all();

        $cardEnabled = $this->cardPaymentAvailable();
        $gatewayEnabled = $this->paymentGatewayAvailable();
        $cardCurrencies = array_values(array_intersect($supportedFiat, ['NGN', 'USD', 'ZAR']));
        $gatewayCurrencies = array_values(array_intersect($supportedFiat, ['NGN']));

        return collect([
            [
                'id' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'description' => 'Fund through supported bank rails with a dedicated payment reference.',
                'status' => 'available',
                'enabled' => true,
                'currencies' => $supportedFiat,
                'processing_time' => 'Typically within 5 to 30 minutes after payment matching',
                'payment_window_minutes' => 30,
            ],
            [
                'id' => 'card_payment',
                'name' => 'Card Payment',
                'description' => $cardEnabled ? 'Pay instantly through ExaEarn card checkout.' : 'Fast card funding. Configure Flutterwave to activate this rail.',
                'status' => $cardEnabled ? 'available' : 'coming_soon',
                'enabled' => $cardEnabled,
                'currencies' => $cardCurrencies ?: ['NGN'],
                'processing_time' => $cardEnabled ? 'Usually credited after provider confirmation' : 'Unavailable',
                'payment_window_minutes' => $cardEnabled ? 30 : 0,
            ],
            [
                'id' => 'payment_gateway',
                'name' => 'Payment Gateway',
                'description' => $gatewayEnabled ? 'Collect through a provider-backed ExaEarn payment account.' : 'Third-party local payment partner collection. Configure Nomba to activate this rail.',
                'status' => $gatewayEnabled ? 'available' : 'coming_soon',
                'enabled' => $gatewayEnabled,
                'currencies' => $gatewayCurrencies ?: ['NGN'],
                'processing_time' => $gatewayEnabled ? 'Typically within 5 to 30 minutes after provider confirmation' : 'Unavailable',
                'payment_window_minutes' => $gatewayEnabled ? 30 : 0,
            ],
        ]);
    }

    private function buildFiatInstructions(string $methodId, string $currency, string $reference, string $accountTag, array $context = []): array
    {
        if ($methodId === 'bank_transfer') {
            return [
                'type' => 'bank_transfer',
                'bank_name' => match ($currency) {
                    'NGN' => 'ExaEarn Settlement Bank',
                    'USD' => 'ExaEarn USD Collections',
                    'ZAR' => 'ExaEarn Rand Collections',
                    default => 'ExaEarn Collections',
                },
                'account_name' => 'ExaEarn Client Collections',
                'account_number' => match ($currency) {
                    'NGN' => '1029384756',
                    'USD' => '001482937465',
                    'ZAR' => '4457281901',
                    default => 'Pending assignment',
                },
                'reference' => $reference,
                'beneficiary_code' => $accountTag,
                'narrative' => sprintf('Use %s as your payment reference.', $reference),
            ];
        }

        if ($methodId === 'payment_gateway') {
            $virtualAccount = $context['virtual_account'] ?? [];
            return [
                'type' => 'payment_gateway',
                'provider' => (string) ($virtualAccount['provider'] ?? 'gateway'),
                'bank_name' => (string) ($virtualAccount['bank_name'] ?? 'ExaEarn Gateway Collections'),
                'account_name' => (string) ($virtualAccount['account_name'] ?? 'ExaEarn Client Collections'),
                'account_number' => (string) ($virtualAccount['account_number'] ?? 'Pending assignment'),
                'reference' => $reference,
                'beneficiary_code' => $accountTag,
                'narrative' => sprintf('Pay into the provider-backed collection account and keep %s as your reconciliation reference.', $reference),
            ];
        }

        if ($methodId === 'card_payment') {
            return [
                'type' => 'card_payment',
                'reference' => $reference,
                'beneficiary_code' => $accountTag,
                'narrative' => 'Continue to the secure hosted card checkout to complete this deposit.',
            ];
        }

        return [
            'type' => $methodId,
            'reference' => $reference,
            'narrative' => 'Follow the selected ExaEarn fiat funding instructions before sending payment.',
        ];
    }

    private function networkCatalogForAsset(string $currency)
    {
        $definitions = [
            'BTC' => [['id' => 'bitcoin', 'name' => 'Bitcoin', 'standard' => 'BTC', 'minimumDeposit' => '0.0001', 'depositConfirmations' => (int) config('wallet.confirmations.bitcoin', 3), 'withdrawalUnlockConfirmations' => 3, 'estimatedArrival' => '3 confirmations', 'memoRequired' => false, 'status' => 'available']],
            'ETH' => [['id' => 'ethereum', 'name' => 'Ethereum', 'standard' => 'ERC20', 'minimumDeposit' => '0.001', 'depositConfirmations' => (int) config('wallet.confirmations.ethereum', 12), 'withdrawalUnlockConfirmations' => 12, 'estimatedArrival' => '12 confirmations', 'memoRequired' => false, 'status' => 'available']],
            'BNB' => [['id' => 'bsc', 'name' => 'BNB Smart Chain', 'standard' => 'BEP20', 'minimumDeposit' => '0.01', 'depositConfirmations' => (int) config('wallet.confirmations.bsc', 15), 'withdrawalUnlockConfirmations' => 15, 'estimatedArrival' => '15 confirmations', 'memoRequired' => false, 'status' => 'available']],
            'MATIC' => [['id' => 'polygon', 'name' => 'Polygon', 'standard' => 'POL', 'minimumDeposit' => '1', 'depositConfirmations' => (int) config('wallet.confirmations.polygon', 20), 'withdrawalUnlockConfirmations' => 20, 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available']],
            'XRP' => [['id' => 'xrpl', 'name' => 'XRP Ledger', 'standard' => 'XRP', 'minimumDeposit' => '10', 'depositConfirmations' => (int) config('wallet.confirmations.xrpl', 1), 'withdrawalUnlockConfirmations' => 1, 'estimatedArrival' => '1 confirmation', 'memoRequired' => true, 'status' => 'available']],
            'TRX' => [['id' => 'tron', 'name' => 'TRON', 'standard' => 'TRC20', 'minimumDeposit' => '1', 'depositConfirmations' => (int) config('wallet.confirmations.tron', 20), 'withdrawalUnlockConfirmations' => 20, 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available']],
            'SOL' => [['id' => 'solana', 'name' => 'Solana', 'standard' => 'SOL', 'minimumDeposit' => '0.01', 'depositConfirmations' => (int) config('wallet.confirmations.solana', 20), 'withdrawalUnlockConfirmations' => 20, 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available']],
            'TON' => [['id' => 'ton', 'name' => 'TON', 'standard' => 'TON', 'minimumDeposit' => '0.01', 'depositConfirmations' => (int) config('wallet.confirmations.ton', 20), 'withdrawalUnlockConfirmations' => 20, 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available']],
            'USDT' => [
                ['id' => 'tron', 'name' => 'TRON', 'standard' => 'TRC20', 'minimumDeposit' => '1', 'depositConfirmations' => (int) config('wallet.confirmations.tron', 20), 'withdrawalUnlockConfirmations' => 20, 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available'],
                ['id' => 'ethereum', 'name' => 'Ethereum', 'standard' => 'ERC20', 'minimumDeposit' => '5', 'depositConfirmations' => (int) config('wallet.confirmations.ethereum', 12), 'withdrawalUnlockConfirmations' => 12, 'estimatedArrival' => '12 confirmations', 'memoRequired' => false, 'status' => 'available'],
                ['id' => 'bsc', 'name' => 'BNB Smart Chain', 'standard' => 'BEP20', 'minimumDeposit' => '1', 'depositConfirmations' => (int) config('wallet.confirmations.bsc', 15), 'withdrawalUnlockConfirmations' => 15, 'estimatedArrival' => '15 confirmations', 'memoRequired' => false, 'status' => 'available'],
                ['id' => 'solana', 'name' => 'Solana', 'standard' => 'SPL', 'minimumDeposit' => '1', 'depositConfirmations' => (int) config('wallet.confirmations.solana', 20), 'withdrawalUnlockConfirmations' => 20, 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available'],
                ['id' => 'polygon', 'name' => 'Polygon', 'standard' => 'POL', 'minimumDeposit' => '1', 'depositConfirmations' => (int) config('wallet.confirmations.polygon', 20), 'withdrawalUnlockConfirmations' => 20, 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available'],
            ],
            'USDC' => [
                ['id' => 'ethereum', 'name' => 'Ethereum', 'standard' => 'ERC20', 'minimumDeposit' => '5', 'depositConfirmations' => (int) config('wallet.confirmations.ethereum', 12), 'withdrawalUnlockConfirmations' => 12, 'estimatedArrival' => '12 confirmations', 'memoRequired' => false, 'status' => 'available'],
                ['id' => 'base', 'name' => 'Base', 'standard' => 'Base', 'minimumDeposit' => '1', 'depositConfirmations' => (int) config('wallet.confirmations.base', 12), 'withdrawalUnlockConfirmations' => 12, 'estimatedArrival' => '12 confirmations', 'memoRequired' => false, 'status' => 'available'],
                ['id' => 'solana', 'name' => 'Solana', 'standard' => 'SPL', 'minimumDeposit' => '1', 'depositConfirmations' => (int) config('wallet.confirmations.solana', 20), 'withdrawalUnlockConfirmations' => 20, 'estimatedArrival' => '20 confirmations', 'memoRequired' => false, 'status' => 'available'],
            ],
            'EXA' => [['id' => 'base', 'name' => 'Base', 'standard' => 'EXA', 'minimumDeposit' => '10', 'depositConfirmations' => (int) config('wallet.confirmations.base', 12), 'withdrawalUnlockConfirmations' => 12, 'estimatedArrival' => '12 confirmations', 'memoRequired' => false, 'status' => 'available']],
        ];

        $fallbackNetwork = strtolower((string) (config('wallet.assets.' . $currency . '.network') ?? ''));
        $fallback = $fallbackNetwork !== '' ? [[
            'id' => $fallbackNetwork,
            'name' => strtoupper($fallbackNetwork),
            'standard' => strtoupper($fallbackNetwork),
            'minimumDeposit' => '0',
            'depositConfirmations' => (int) config('wallet.confirmations.' . $fallbackNetwork, 1),
            'withdrawalUnlockConfirmations' => (int) config('wallet.confirmations.' . $fallbackNetwork, 1),
            'estimatedArrival' => (int) config('wallet.confirmations.' . $fallbackNetwork, 1) . ' confirmations',
            'memoRequired' => false,
            'status' => 'available',
        ]] : [];

        return collect($definitions[$currency] ?? $fallback);
    }

    private function cardPaymentAvailable(): bool
    {
        return app()->environment(['local', 'testing'])
            || (((string) config('services.flutterwave.secret_key')) !== '' && ((string) config('services.flutterwave.payment_url', '')) !== '');
    }

    private function paymentGatewayAvailable(): bool
    {
        return app()->environment(['local', 'testing'])
            || (((string) config('services.nomba.token')) !== '' && ((string) config('services.nomba.url')) !== '' && ((string) config('services.nomba.account_id')) !== '');
    }

    private function fiatRedirectUrl(string $reference): string
    {
        $base = rtrim((string) (env('FRONTEND_URL', config('app.url', 'http://localhost'))), '/');
        return $base . '/add-funds?payment_reference=' . urlencode($reference);
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

    private function addDecimal(string $left, string $right): string
    {
        return function_exists('bcadd') ? bcadd($left, $right, 8) : number_format((float) $left + (float) $right, 8, '.', '');
    }
}