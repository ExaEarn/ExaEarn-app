<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WithdrawalEngineService
{
    private const DECIMALS = 18;
    private const CRYPTO_ASSETS = ['BTC', 'ETH', 'USDT', 'USDC', 'XRP', 'SOL'];
    private const FIAT_ASSETS = ['NGN', 'USD', 'EUR', 'GBP', 'ZAR', 'KES'];

    public function __construct(
        private readonly WalletService $walletService,
        private readonly CurrencyConversionService $conversionService,
        private readonly RiskPolicyService $riskPolicy,
    ) {
    }

    /**
     * Validate withdrawal request and detect if swap is required.
     * Returns validation result with swap_required flag if mismatch detected.
     */
    public function validateWithdrawalRequest(
        User $user,
        string $currency,
        string $amount,
        string $destinationType,
        ?string $address = null
    ): array {
        $currency = strtoupper($currency);
        $decimals = self::DECIMALS;

        // 1. Check if user has sufficient balance
        $wallet = Wallet::where('user_id', $user->id)
            ->where('currency', $currency)
            ->first();

        if (!$wallet || bccomp((string) $wallet->available_balance, $amount, $decimals) < 0) {
            throw new RuntimeException("Insufficient balance for {$currency}. Available: " . ($wallet?->available_balance ?? '0'));
        }

        // 2. Determine asset type
        $isSourceCrypto = $this->isCryptoAsset($currency);
        $isDestinationCrypto = $destinationType === 'crypto';

        // 3. CRITICAL: Detect mismatch and require swap
        if ($isSourceCrypto !== $isDestinationCrypto) {
            return [
                'valid' => false,
                'swap_required' => true,
                'message' => $this->buildSwapMessage($currency, $isSourceCrypto),
                'source_asset' => $currency,
                'source_is_crypto' => $isSourceCrypto,
                'source_is_fiat' => !$isSourceCrypto,
                'destination_type' => $destinationType,
                'amount' => $amount,
            ];
        }

        // 4. Validate destination format
        if ($destinationType === 'crypto' && !$address) {
            throw new RuntimeException('Crypto withdrawal requires a wallet address.');
        }

        // 5. Check minimum withdrawal limits
        $minLimit = $this->getMinimumWithdrawal($currency);
        if (bccomp($amount, $minLimit, $decimals) < 0) {
            throw new RuntimeException("Withdrawal amount below minimum ({$minLimit} {$currency}).");
        }

        // 6. Risk policy checks
        $riskAssessment = $this->riskPolicy->assessWithdrawal($user, $currency, $amount);
        if ($riskAssessment['blocked']) {
            throw new RuntimeException($riskAssessment['reason']);
        }

        return [
            'valid' => true,
            'swap_required' => false,
            'message' => 'Withdrawal request validated.',
            'risk_level' => $riskAssessment['level'],
            'requires_2fa' => true,
        ];
    }

    /**
     * Detect if swap is needed before withdrawal.
     */
    public function detectSwapRequirement(
        User $user,
        string $sourceAsset,
        string $targetType
    ): ?array {
        $sourceAsset = strtoupper($sourceAsset);
        $isSourceCrypto = $this->isCryptoAsset($sourceAsset);
        $isTargetCrypto = $targetType === 'crypto';

        // Mismatch: crypto balance but user wants fiat withdrawal (or vice versa)
        if ($isSourceCrypto !== $isTargetCrypto) {
            $targetAsset = $isSourceCrypto ? 'NGN' : 'USDT'; // Default target for swap

            return [
                'source_asset' => $sourceAsset,
                'target_asset' => $targetAsset,
                'message' => $this->buildSwapMessage($sourceAsset, $isSourceCrypto),
                'redirect_to_swap' => true,
            ];
        }

        return null;
    }

    /**
     * Apply withdrawal security requirements.
     * Must verify 2FA before proceeding.
     */
    public function verifySecurityRequirements(
        User $user,
        string $code2FA,
        string $ip,
        string $userAgent
    ): bool {
        // Verify 2FA code (implementation depends on 2FA provider)
        if (!$this->verify2FA($user, $code2FA)) {
            throw new RuntimeException('Invalid 2FA code.');
        }

        // Record security context for risk review and audit trails.
        $this->logSecurityContext($user, $ip, $userAgent);

        return true;
    }

    private function logSecurityContext(User $user, string $ip, string $userAgent): void
    {
        // TODO: Expand this to persist device / IP context for review.

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'withdrawal_security_verified',
            'ip_address' => $ip,
            'device' => $userAgent,
            'metadata' => [
                'verified_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Check if user withdrawal is within policy limits.
     */
    public function checkWithdrawalLimits(User $user, string $currency, string $amount): void
    {
        $decimals = self::DECIMALS;

        // Daily limit
        $dailyWithdrawn = $this->getTodayWithdrawalTotal($user->id, $currency);
        $dailyLimit = $this->getDailyLimit($user);

        $newTotal = bcadd($dailyWithdrawn, $amount, $decimals);
        if (bccomp($newTotal, $dailyLimit, $decimals) > 0) {
            throw new RuntimeException("Withdrawal exceeds daily limit. Withdrawn today: {$dailyWithdrawn}, Limit: {$dailyLimit}");
        }

        // Per-request limit
        $maxPerRequest = $this->getMaxPerRequest($user);
        if (bccomp($amount, $maxPerRequest, $decimals) > 0) {
            throw new RuntimeException("Withdrawal amount exceeds maximum per request ({$maxPerRequest}).");
        }

        // Cooldown check
        if ($user->last_withdrawal_at && $user->last_withdrawal_at->diffInSeconds(now()) < 60) {
            throw new RuntimeException('Withdrawal cooldown active. Please wait 60 seconds.');
        }
    }

    /**
     * Lock funds for withdrawal.
     */
    public function lockWithdrawalFunds(User $user, string $currency, string $amount): void
    {
        $wallet = Wallet::where('user_id', $user->id)
            ->where('currency', strtoupper($currency))
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            throw new RuntimeException("Wallet not found for {$currency}");
        }

        $wallet->available_balance = bcsub((string) $wallet->available_balance, $amount, self::DECIMALS);
        $wallet->locked_balance = bcadd((string) $wallet->locked_balance, $amount, self::DECIMALS);
        $wallet->save();
    }

    /**
     * Release locked funds (if withdrawal is cancelled).
     */
    public function releaseLockedFunds(User $user, string $currency, string $amount): void
    {
        $wallet = Wallet::where('user_id', $user->id)
            ->where('currency', strtoupper($currency))
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            return;
        }

        $wallet->locked_balance = bcsub((string) $wallet->locked_balance, $amount, self::DECIMALS);
        $wallet->available_balance = bcadd((string) $wallet->available_balance, $amount, self::DECIMALS);
        $wallet->save();
    }

    // ────────────────────── Private Helpers ──────────────────────

    private function isCryptoAsset(string $asset): bool
    {
        return in_array(strtoupper($asset), self::CRYPTO_ASSETS, true);
    }

    private function buildSwapMessage(string $currency, bool $isSourceCrypto): string
    {
        if ($isSourceCrypto) {
            return "You are trying to withdraw crypto but your balance is in {$currency}. Please convert {$currency} → USDT to continue.";
        }

        return "You are trying to withdraw to fiat but your balance is in {$currency}. Please convert {$currency} → NGN to continue.";
    }

    private function getMinimumWithdrawal(string $currency): string
    {
        return match (strtoupper($currency)) {
            'NGN' => '1000',
            'USD' => '10',
            'USDT', 'USDC' => '10',
            'BTC' => '0.001',
            'ETH' => '0.01',
            default => '10',
        };
    }

    private function getDailyLimit(User $user): string
    {
        $kycLevel = (int) ($user->kyc_level ?? 0);
        $limits = (array) config('kyc.limits', []);

        if (array_key_exists($kycLevel, $limits)) {
            return (string) $limits[$kycLevel];
        }

        return (string) config('wallet.withdrawals.daily_limit', '25000');
    }

    private function getMaxPerRequest(User $user): string
    {
        return config('wallet.withdrawals.max_per_request', '10000');
    }

    private function getTodayWithdrawalTotal(int $userId, string $currency): string
    {
        $total = \App\Models\Withdrawal::where('user_id', $userId)
            ->where('currency', strtoupper($currency))
            ->whereDate('created_at', today())
            ->whereIn('status', ['pending', 'processing', 'completed'])
            ->sum('amount');

        return (string) $total;
    }

    private function verify2FA(User $user, string $code): bool
    {
        if (!$user->two_factor_enabled || !$user->two_factor_secret) {
            return false;
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return $this->verifyTotp((string) $user->two_factor_secret, $code);
    }

    private function verifyTotp(string $secret, string $code, int $window = 1, int $digits = 6, int $period = 30): bool
    {
        if ($secret === '' || !preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $binarySecret = $this->base32Decode($secret);
        if ($binarySecret === '') {
            return false;
        }

        $counter = (int) floor(time() / $period);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $expected = $this->generateHotp($binarySecret, $counter + $offset, $digits);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    private function generateHotp(string $secret, int $counter, int $digits): string
    {
        $counterBytes = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $counterBytes, $secret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $slice = substr($hash, $offset, 4);
        $value = unpack('N', $slice)[1] & 0x7FFFFFFF;
        $mod = 10 ** $digits;

        return str_pad((string) ($value % $mod), $digits, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');

        if ($clean === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($clean) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) {
                return '';
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }
}
