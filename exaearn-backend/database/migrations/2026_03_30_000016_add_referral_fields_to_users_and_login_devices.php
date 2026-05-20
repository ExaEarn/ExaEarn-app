<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 32)->nullable()->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            $table->timestamp('kyc_verified_at')->nullable()->after('phone_verified_at');
            $table->timestamp('reward_suspended_until')->nullable()->after('last_withdrawal_at');
            $table->jsonb('reward_risk_flags')->nullable()->after('reward_suspended_until');
        });

        Schema::table('login_devices', function (Blueprint $table) {
            $table->string('fingerprint_hash', 64)->nullable()->after('ip_address');
            $table->index('fingerprint_hash');
        });
    }

    public function down(): void
    {
        Schema::table('login_devices', function (Blueprint $table) {
            $table->dropIndex(['fingerprint_hash']);
            $table->dropColumn('fingerprint_hash');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'referral_code',
                'phone_verified_at',
                'kyc_verified_at',
                'reward_suspended_until',
                'reward_risk_flags',
            ]);
        });
    }
};

