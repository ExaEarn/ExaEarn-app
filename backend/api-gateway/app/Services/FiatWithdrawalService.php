<?php

namespace App\Services;

use App\Models\Withdrawal;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * FiatWithdrawalService
 *
 * Handles fiat withdrawal processing via payment gateways (Flutterwave, Nomba).
 * Manages bank account verification, transaction initiation, and webhook handling
 * for payment status updates.
 */
class FiatWithdrawalService
{
    public const GATEWAY_FLUTTERWAVE = 'flutterwave';
    public const GATEWAY_NOMBA = 'nomba';

    private string $primaryGateway;
    private string $fallbackGateway;
    private WithdrawalService $withdrawalService;


    public function __construct(WithdrawalService $withdrawalService)
    {
        $this->primaryGateway = config('services.fiat_gateway.primary', self::GATEWAY_FLUTTERWAVE);
        $this->fallbackGateway = config('services.fiat_gateway.fallback', self::GATEWAY_NOMBA);
        $this->withdrawalService = $withdrawalService;
    }

    /**
     * Initiate fiat withdrawal to bank account.
     *
     * @throws \RuntimeException
     */
    public function initiate(
        User $user,
        Withdrawal $withdrawal,
        string $bankCode,
        string $accountNumber,
        ?string $currency = 'NGN',
        string $payoutMethod = 'Bank Account',
        string $swiftCode = ''
    ): array {
        Log::info('Fiat withdrawal initiated', [
            'user_id' => $user->id,
            'withdrawal_id' => $withdrawal->id,
            'bank_code' => $bankCode,
            'amount' => $withdrawal->amount,
        ]);

        // ── 1. VERIFY BANK ACCOUNT ────────────────────────────────
        try {
            $accountName = $this->verifyBankAccount($bankCode, $accountNumber);
            Log::info('Bank account verified', [
                'user_id' => $user->id,
                'account_name' => $accountName,
            ]);
        } catch (\RuntimeException $e) {
            Log::warning('Bank account verification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Unable to verify bank account. Please check details and try again.');
        }

        // ── 2. CHECK FOR DUPLICATE WITHIN COOLDOWN ────────────────
        $recentWithdrawal = Withdrawal::where('user_id', $user->id)
            ->where('metadata->bank_code', $bankCode)
            ->where('metadata->account_number', substr($accountNumber, -4))
            ->where('created_at', '>', now()->subHours(24))
            ->first();

        if ($recentWithdrawal && bccomp((string) $recentWithdrawal->amount, (string) $withdrawal->amount, 2) === 0) {
            Log::warning('Duplicate withdrawal attempt detected', [
                'user_id' => $user->id,
                'recent_withdrawal_id' => $recentWithdrawal->id,
            ]);
            throw new \RuntimeException('Similar withdrawal initiated recently. Please wait before retrying.');
        }

        // ── 3. INITIATE PAYMENT ───────────────────────────────────
        $gateway = $this->primaryGateway;
        try {
            $paymentResult = match ($gateway) {
                self::GATEWAY_FLUTTERWAVE => $this->initiateFlutterwave($user, $withdrawal, $bankCode, $accountNumber, $currency, $swiftCode),
                self::GATEWAY_NOMBA => $this->initiateNomba($user, $withdrawal, $bankCode, $accountNumber, $currency, $swiftCode),
                default => throw new \RuntimeException('Unsupported payment gateway.'),
            };
        } catch (\RuntimeException $e) {
            Log::warning("Payment gateway {$gateway} failed", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            // Attempt fallback gateway
            if ($gateway !== $this->fallbackGateway) {
                Log::info('Attempting fallback gateway', [
                    'user_id' => $user->id,
                    'fallback_gateway' => $this->fallbackGateway,
                ]);

                $gateway = $this->fallbackGateway;
                try {
                    $paymentResult = match ($gateway) {
                        self::GATEWAY_NOMBA => $this->initiateNomba($user, $withdrawal, $bankCode, $accountNumber, $currency, $swiftCode),
                        self::GATEWAY_FLUTTERWAVE => $this->initiateFlutterwave($user, $withdrawal, $bankCode, $accountNumber, $currency, $swiftCode),
                        default => throw new \RuntimeException('Unsupported fallback gateway.'),
                    };
                } catch (\RuntimeException $fallbackError) {
                    Log::error('Both payment gateways failed', [
                        'user_id' => $user->id,
                        'primary_error' => $e->getMessage(),
                        'fallback_error' => $fallbackError->getMessage(),
                    ]);
                    throw new \RuntimeException('Payment processing unavailable. Please try again later.');
                }
            } else {
                throw $e;
            }
        }

        // ── 4. UPDATE WITHDRAWAL WITH PAYMENT REFERENCE ───────────
        $withdrawal->update([
            'status' => 'processing',
            'metadata' => array_merge($withdrawal->metadata ?? [], [
                'gateway' => $gateway,
                'gateway_reference_id' => $paymentResult['reference_id'],
                'account_name' => $accountName,
                'payout_method' => $payoutMethod,
                'swift_code' => $swiftCode ?: ($withdrawal->metadata['swift_code'] ?? null),
                'initiated_at' => now()->toISOString(),
                'gateway_response' => $paymentResult,
            ]),
        ]);

        Log::info('Fiat withdrawal queued for processing', [
            'withdrawal_id' => $withdrawal->id,
            'gateway' => $gateway,
            'reference_id' => $paymentResult['reference_id'],
        ]);

        return [
            'withdrawal_id' => $withdrawal->id,
            'reference_id' => $paymentResult['reference_id'],
            'gateway' => $gateway,
            'status' => $withdrawal->status,
            'estimated_arrival' => $paymentResult['estimated_arrival'] ?? '1-3 business days',
        ];
    }

    /**
     * Initiate a fiat withdrawal by creating the transaction and withdrawal record.
     */
    public function initiateFiatWithdrawal(
        User $user,
        string $amount,
        string $currency,
        string $bankCode,
        string $accountNumber,
        string $accountName,
        ?string $narration = null,
        string $payoutMethod = 'Bank Account'
    ): array {
        $transaction = $this->withdrawalService->request(
            $user->id,
            strtoupper($currency),
            $amount,
            null,
            [
                'bank_code' => $bankCode,
                'account_number' => $accountNumber,
                'destination_type' => 'bank',
                'account_name' => $accountName,
                'narration' => $narration,
            ]
        );

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'transaction_id' => $transaction->id,
            'currency' => strtoupper($currency),
            'amount' => $amount,
            'fee' => '0',
            'address' => null,
            'network' => null,
            'status' => 'pending',
            'metadata' => [
                'bank_code' => $bankCode,
                'account_number' => substr($accountNumber, -4) . '****',
                'account_name' => $accountName,
                'narration' => $narration,
                'payout_method' => $payoutMethod,
            ],
        ]);

        $paymentResult = $this->initiate(
            $user,
            $withdrawal,
            $bankCode,
            $accountNumber,
            strtoupper($currency),
            $withdrawal->metadata['payout_method'] ?? 'Bank Account'
        );

        return array_merge($paymentResult, ['withdrawal' => $withdrawal]);
    }

    /**
     * Verify bank account exists and is valid via Flutterwave API.
     *
     * @throws \RuntimeException
     */
    private function verifyBankAccount(string $bankCode, string $accountNumber): string
    {
        $cacheKey = "bank_verify:{$bankCode}:{$accountNumber}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached['account_name'];
        }

        try {
            $response = Http::withToken(config('services.flutterwave.secret_key'))
                ->get('https://api.flutterwave.com/v3/accounts/resolve', [
                    'account_number' => $accountNumber,
                    'account_bank' => $bankCode,
                ])
                ->throw();

            $data = $response->json();
            if (!($data['status'] === 'success' && ($data['data']['account_status'] ?? false))) {
                throw new \RuntimeException('Bank account verification failed.');
            }

            $accountName = $data['data']['account_name'] ?? 'Unknown';

            // Cache for 24 hours
            Cache::put($cacheKey, [
                'account_name' => $accountName,
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ], 86400);

            return $accountName;
        } catch (\Exception $e) {
            Log::error('Bank account verification error', [
                'bank_code' => $bankCode,
                'account_number' => substr($accountNumber, -4),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Unable to verify bank account.');
        }
    }

    /**
     * Initiate transfer via Flutterwave API.
     */
    private function initiateFlutterwave(
        User $user,
        Withdrawal $withdrawal,
        string $bankCode,
        string $accountNumber,
        string $currency = 'NGN'
    ): array {
        try {
            $response = Http::withToken(config('services.flutterwave.secret_key'))
                ->post('https://api.flutterwave.com/v3/transfers', [
                    'account_bank' => $bankCode,
                    'account_number' => $accountNumber,
                    'amount' => (int) $withdrawal->amount,
                    'currency' => $currency,
                    'reference' => "WD-{$withdrawal->id}-" . time(),
                    'narration' => "ExaEarn withdrawal - {$user->email}",
                    'meta' => [
                        'user_id' => $user->id,
                        'withdrawal_id' => $withdrawal->id,
                    ],
                ])
                ->throw();

            $data = $response->json();

            if ($data['status'] !== 'success') {
                throw new \RuntimeException($data['message'] ?? 'Transfer initiation failed.');
            }

            return [
                'reference_id' => $data['data']['reference'] ?? "FW-{$data['data']['id']}",
                'transfer_id' => $data['data']['id'],
                'status' => $data['data']['status'],
                'estimated_arrival' => '1-3 business days',
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave transfer initiation failed', [
                'user_id' => $user->id,
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Flutterwave transfer failed: ' . $e->getMessage());
        }
    }

    /**
     * Initiate transfer via Nomba API.
     */
    private function initiateNomba(
        User $user,
        Withdrawal $withdrawal,
        string $bankCode,
        string $accountNumber,
        string $currency = 'NGN'
    ): array {
        try {
            $response = Http::withToken(config('services.nomba.api_key'))
                ->post('https://api.nomba.com/v1/bank-transfers', [
                    'account' => [
                        'accountNumber' => $accountNumber,
                        'bankCode' => $bankCode,
                    ],
                    'amount' => (float) $withdrawal->amount,
                    'currency' => $currency,
                    'reference' => "WD-{$withdrawal->id}-" . time(),
                    'description' => "ExaEarn withdrawal",
                    'metadata' => [
                        'user_id' => $user->id,
                        'withdrawal_id' => $withdrawal->id,
                        'email' => $user->email,
                    ],
                ])
                ->throw();

            $data = $response->json();

            if (!($data['status'] === 'success' || $data['status'] === 'pending')) {
                throw new \RuntimeException($data['message'] ?? 'Transfer initiation failed.');
            }

            return [
                'reference_id' => $data['data']['transactionReference'] ?? $data['data']['id'],
                'transfer_id' => $data['data']['id'],
                'status' => $data['status'],
                'estimated_arrival' => '1-3 business days',
            ];
        } catch (\Exception $e) {
            Log::error('Nomba transfer initiation failed', [
                'user_id' => $user->id,
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Nomba transfer failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle webhook callback from Flutterwave with transfer status update.
     */
    public function handleFlutterwaveWebhook(array $payload): void
    {
        Log::info('Flutterwave webhook received', [
            'event_type' => $payload['event'] ?? null,
            'transfer_id' => $payload['data']['id'] ?? null,
        ]);

        $reference = $payload['data']['reference'] ?? null;
        if (!$reference) {
            return;
        }

        // Extract withdrawal ID from reference: WD-{id}-{timestamp}
        if (!preg_match('/^WD-(\d+)-/', $reference, $matches)) {
            return;
        }

        $withdrawalId = (int) $matches[1];
        $withdrawal = Withdrawal::find($withdrawalId);
        if (!$withdrawal) {
            Log::warning('Withdrawal not found for webhook', ['withdrawal_id' => $withdrawalId]);
            return;
        }

        $status = match ($payload['data']['status'] ?? null) {
            'successful' => 'completed',
            'failed' => 'failed',
            'pending' => 'processing',
            default => 'pending',
        };

        $withdrawal->update([
            'status' => $status,
            'tx_hash' => $payload['data']['id'],
            'metadata' => array_merge($withdrawal->metadata ?? [], [
                'gateway_status_update' => $payload['data']['status'],
                'updated_at' => now()->toISOString(),
                'webhook_payload' => $payload,
            ]),
        ]);

        Log::info('Withdrawal status updated via webhook', [
            'withdrawal_id' => $withdrawal->id,
            'new_status' => $status,
        ]);
    }

    /**
     * Handle webhook callback from Nomba with transfer status update.
     */
    public function handleNombaWebhook(array $payload): void
    {
        Log::info('Nomba webhook received', [
            'event_type' => $payload['eventType'] ?? null,
            'transaction_reference' => $payload['data']['transactionReference'] ?? null,
        ]);

        $reference = $payload['data']['reference'] ?? $payload['data']['transactionReference'] ?? null;
        if (!$reference) {
            return;
        }

        // Extract withdrawal ID from reference: WD-{id}-{timestamp}
        if (!preg_match('/^WD-(\d+)-/', $reference, $matches)) {
            return;
        }

        $withdrawalId = (int) $matches[1];
        $withdrawal = Withdrawal::find($withdrawalId);
        if (!$withdrawal) {
            Log::warning('Withdrawal not found for webhook', ['withdrawal_id' => $withdrawalId]);
            return;
        }

        $status = match ($payload['data']['status'] ?? null) {
            'completed', 'success' => 'completed',
            'failed' => 'failed',
            'pending' => 'processing',
            default => 'pending',
        };

        $withdrawal->update([
            'status' => $status,
            'tx_hash' => $payload['data']['transactionId'],
            'metadata' => array_merge($withdrawal->metadata ?? [], [
                'gateway_status_update' => $payload['data']['status'],
                'updated_at' => now()->toISOString(),
                'webhook_payload' => $payload,
            ]),
        ]);

        Log::info('Withdrawal status updated via webhook', [
            'withdrawal_id' => $withdrawal->id,
            'new_status' => $status,
        ]);
    }

    /**
     * Get list of supported Nigerian banks with codes.
     */
    public function getSupportedBanks(): array
    {
        $cacheKey = 'supported_banks_list';
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            $response = Http::withToken(config('services.flutterwave.secret_key'))
                ->get('https://api.flutterwave.com/v3/banks/NG')
                ->throw();

            $data = $response->json();
            $banks = array_map(fn($bank) => [
                'code' => $bank['code'],
                'name' => $bank['name'],
            ], $data['data'] ?? []);

            // Cache for 24 hours
            Cache::put($cacheKey, $banks, 86400);

            return $banks;
        } catch (\Exception $e) {
            Log::error('Failed to fetch bank list', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Check withdrawal status from gateway.
     */
    public function checkWithdrawalStatus(Withdrawal $withdrawal): ?array
    {
        if (!$withdrawal->metadata || !isset($withdrawal->metadata['gateway'])) {
            return null;
        }

        $gateway = $withdrawal->metadata['gateway'];
        $referenceId = $withdrawal->metadata['gateway_reference_id'] ?? null;

        if (!$referenceId) {
            return null;
        }

        try {
            $result = match ($gateway) {
                self::GATEWAY_FLUTTERWAVE => $this->checkFlutterwaveStatus($referenceId),
                self::GATEWAY_NOMBA => $this->checkNombaStatus($referenceId),
                default => null,
            };

            if ($result) {
                $withdrawal->update([
                    'status' => $result['status'],
                    'metadata' => array_merge((array) $withdrawal->metadata, [
                        'last_status_check' => now()->toISOString(),
                        'gateway_response' => $result,
                    ]),
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::warning('Failed to check withdrawal status', [
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function checkFlutterwaveStatus(string $referenceId): ?array
    {
        $response = Http::withToken(config('services.flutterwave.secret_key'))
            ->get("https://api.flutterwave.com/v3/transfers/{$referenceId}")
            ->throw();

        $data = $response->json();
        if (!($data['status'] === 'success')) {
            return null;
        }

        return [
            'status' => match ($data['data']['status']) {
                'successful' => 'completed',
                'failed' => 'failed',
                'pending' => 'processing',
                default => 'pending',
            },
            'gateway_status' => $data['data']['status'],
            'amount' => $data['data']['amount'],
        ];
    }

    private function checkNombaStatus(string $referenceId): ?array
    {
        $response = Http::withToken(config('services.nomba.api_key'))
            ->get("https://api.nomba.com/v1/transactions/{$referenceId}")
            ->throw();

        $data = $response->json();
        if (!($data['status'] === 'success')) {
            return null;
        }

        return [
            'status' => match ($data['data']['status']) {
                'completed', 'success' => 'completed',
                'failed' => 'failed',
                'pending' => 'processing',
                default => 'pending',
            },
            'gateway_status' => $data['data']['status'],
            'amount' => $data['data']['amount'],
        ];
    }
}
