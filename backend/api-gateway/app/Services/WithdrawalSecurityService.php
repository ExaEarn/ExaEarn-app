<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SecurityWithdrawalAddress;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Str;

class WithdrawalSecurityService
{
    public function __construct(
        private readonly SecurityRiskEngine $risk,
        private readonly SecuritySignalService $signals,
    ) {
    }

    public function registerAddress(User $user, string $asset, ?string $network, string $address): SecurityWithdrawalAddress
    {
        $hash = hash('sha256', strtolower(trim($address)));

        return SecurityWithdrawalAddress::query()->updateOrCreate([
            'user_id' => $user->id,
            'asset' => strtoupper($asset),
            'network' => $network ? strtoupper($network) : null,
            'address_hash' => $hash,
        ], [
            'address_uuid' => (string) (SecurityWithdrawalAddress::query()->where('user_id', $user->id)->where('asset', strtoupper($asset))->where('network', $network ? strtoupper($network) : null)->where('address_hash', $hash)->value('address_uuid') ?: Str::uuid()),
            'allowlist_state' => 'UNKNOWN',
            'risk_flags' => [],
            'first_seen_at' => SecurityWithdrawalAddress::query()->where('user_id', $user->id)->where('address_hash', $hash)->value('first_seen_at') ?: now(),
            'last_used_at' => now(),
        ]);
    }

    public function evaluate(User $user, string $asset, string $amount, ?string $network = null, ?string $address = null): array
    {
        if ($address) {
            $known = $this->registerAddress($user, $asset, $network, $address);
            if ((int) $known->successful_withdrawals === 0) {
                $this->signals->record('WITHDRAWAL_ADDRESS_NEW', 'WITHDRAWAL', 'USER', $user->id, 'MEDIUM', ['asset' => $asset, 'network' => $network], '0.8000', 86400);
            }
        }

        $countHour = Withdrawal::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        if ($countHour >= (int) config('security.transactions.max_withdrawal_per_minute', 3)) {
            $this->signals->record('WITHDRAWAL_VELOCITY', 'WITHDRAWAL', 'USER', $user->id, 'HIGH', ['count_hour' => $countHour], '0.9000', 3600);
        }

        return $this->risk->assessWithdrawal($user, $amount, ['asset' => $asset, 'network' => $network]);
    }
}
