<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;

class ActivityAuditService
{
    /**
     * Log user activity
     *
     * @param int $userId
     * @param string $type auth, wallet, trade, reward, staking, nft, security, system
     * @param string $action login, logout, withdrawal, deposit, order_created, etc
     * @param array $data Additional context data (JSON)
     * @param string $status success, failed, pending
     * @return ActivityLog
     */
    public function logUser(
        int $userId,
        string $type,
        string $action,
        array $data = [],
        string $status = 'success'
    ): ActivityLog {
        return $this->createLog(
            userId: $userId,
            adminId: null,
            type: $type,
            action: $action,
            data: $data,
            status: $status
        );
    }

    /**
     * Log admin activity
     *
     * @param int $adminId
     * @param string $action adjust_balance, ban_user, approve_withdrawal, edit_reward, etc
     * @param array $data Additional context
     * @param int|null $userId The user being acted upon (optional)
     * @return ActivityLog
     */
    public function logAdmin(
        int $adminId,
        string $action,
        array $data = [],
        ?int $userId = null
    ): ActivityLog {
        return $this->createLog(
            userId: $userId,
            adminId: $adminId,
            type: 'admin',
            action: $action,
            data: $data,
            status: 'success'
        );
    }

    /**
     * Log system activity
     *
     * @param string $action migration, backup, settings_change, etc
     * @param array $data
     * @param string $status
     * @return ActivityLog
     */
    public function logSystem(
        string $action,
        array $data = [],
        string $status = 'success'
    ): ActivityLog {
        return $this->createLog(
            userId: null,
            adminId: null,
            type: 'system',
            action: $action,
            data: $data,
            status: $status
        );
    }

    /**
     * Log authentication event
     *
     * @param int $userId
     * @param string $action login, logout, login_failed, password_change, email_change
     * @param array $data
     * @param string $status
     * @return ActivityLog
     */
    public function logAuth(
        int $userId,
        string $action,
        array $data = [],
        string $status = 'success'
    ): ActivityLog {
        return $this->logUser($userId, 'auth', $action, $data, $status);
    }

    /**
     * Log wallet event
     *
     * @param int $userId
     * @param string $action deposit, withdrawal, transfer, address_change, sweep
     * @param array $data amount, asset, address, txid, etc
     * @param string $status
     * @return ActivityLog
     */
    public function logWallet(
        int $userId,
        string $action,
        array $data = [],
        string $status = 'success'
    ): ActivityLog {
        return $this->logUser($userId, 'wallet', $action, $data, $status);
    }

    /**
     * Log trade event
     *
     * @param int $userId
     * @param string $action order_created, order_filled, order_cancelled, liquidation, etc
     * @param array $data pair, price, amount, order_id, etc
     * @param string $status
     * @return ActivityLog
     */
    public function logTrade(
        int $userId,
        string $action,
        array $data = [],
        string $status = 'success'
    ): ActivityLog {
        return $this->logUser($userId, 'trade', $action, $data, $status);
    }

    /**
     * Log reward event
     *
     * @param int $userId
     * @param string $action checkin_reward, mission_reward, referral_reward, staking_reward, mystery_box
     * @param array $data amount, asset, reason, etc
     * @param string $status
     * @return ActivityLog
     */
    public function logReward(
        int $userId,
        string $action,
        array $data = [],
        string $status = 'success'
    ): ActivityLog {
        return $this->logUser($userId, 'reward', $action, $data, $status);
    }

    /**
     * Log staking event
     *
     * @param int $userId
     * @param string $action stake, unstake, claim_reward, compound, etc
     * @param array $data amount, pool_id, duration, reward, etc
     * @param string $status
     * @return ActivityLog
     */
    public function logStaking(
        int $userId,
        string $action,
        array $data = [],
        string $status = 'success'
    ): ActivityLog {
        return $this->logUser($userId, 'staking', $action, $data, $status);
    }

    /**
     * Log NFT event
     *
     * @param int $userId
     * @param string $action mint, buy, sell, transfer, stake, list, delist
     * @param array $data nft_id, collection, price, txid, etc
     * @param string $status
     * @return ActivityLog
     */
    public function logNft(
        int $userId,
        string $action,
        array $data = [],
        string $status = 'success'
    ): ActivityLog {
        return $this->logUser($userId, 'nft', $action, $data, $status);
    }

    /**
     * Log security event
     *
     * @param int $userId
     * @param string $action 2fa_enabled, 2fa_disabled, password_changed, email_changed, device_added, suspicious_login
     * @param array $data new_email, device_info, ip, etc
     * @param string $status
     * @return ActivityLog
     */
    public function logSecurity(
        int $userId,
        string $action,
        array $data = [],
        string $status = 'success'
    ): ActivityLog {
        return $this->logUser($userId, 'security', $action, $data, $status);
    }

    /**
     * Create the activity log record
     */
    private function createLog(
        ?int $userId,
        ?int $adminId,
        string $type,
        string $action,
        array $data = [],
        string $status = 'success'
    ): ActivityLog {
        $request = $this->getRequest();

        return ActivityLog::query()->create([
            'user_id' => $userId,
            'admin_id' => $adminId,
            'type' => $type,
            'action' => $action,
            'ip' => $request?->ip() ?? request()?->ip(),
            'device' => $request?->userAgent() ?? request()?->userAgent(),
            'data' => !empty($data) ? $data : null,
            'status' => $status,
        ]);
    }

    /**
     * Get current request if available
     */
    private function getRequest(): ?Request
    {
        try {
            return RequestFacade::instance();
        } catch (\Exception) {
            return null;
        }
    }
}
