<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'unique_user_id',
        'role',
        'two_factor_enabled',
        'two_factor_secret',
        'withdrawal_locked_until',
        'last_withdrawal_at',
        'referral_code',
        'phone_verified_at',
        'kyc_verified_at',
        'kyc_level',
        'reward_suspended_until',
        'reward_risk_flags',
        'preferences',
        'profile_image_url',
        'profile_thumbnail_url',
        'avatar_id',
        'profile_display_type',
        'profile_visibility',
        'profile_image_status',
        'profile_image_updated_at',
        'profile_image_privileges_suspended_until',
        'profile_image_moderation_note',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
            'two_factor_enabled' => 'boolean',
            'withdrawal_locked_until' => 'datetime',
            'last_withdrawal_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'kyc_verified_at' => 'datetime',
            'kyc_level' => 'integer',
            'reward_suspended_until' => 'datetime',
            'reward_risk_flags' => 'array',
            'preferences' => 'array',
            'profile_image_updated_at' => 'datetime',
            'profile_image_privileges_suspended_until' => 'datetime',
        ];
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function loginDevices(): HasMany
    {
        return $this->hasMany(LoginDevice::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_user_id');
    }

    public function referralRewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class, 'referrer_id');
    }

    public function athleteProfiles(): HasMany
    {
        return $this->hasMany(Athlete::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function tradingProfile(): HasOne
    {
        return $this->hasOne(UserTradingProfile::class);
    }

    public function autoTradingStrategies(): HasMany
    {
        return $this->hasMany(AutoTradingStrategy::class);
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AIAssistantConversation::class);
    }

    public function exaAiSubscriptions(): HasMany
    {
        return $this->hasMany(ExaAiSubscription::class);
    }

    public function exaAiAllocations(): HasMany
    {
        return $this->hasMany(ExaAiCapitalAllocation::class);
    }

    public function exaAiSessions(): HasMany
    {
        return $this->hasMany(ExaAiSession::class);
    }

    public function exaAiOrders(): HasMany
    {
        return $this->hasMany(ExaAiOrder::class);
    }
}
