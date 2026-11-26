<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_launch_events', function (Blueprint $table): void {
            $table->string('operation_status', 64)->default('SCHEDULED')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('configuration_fingerprint', 64)->nullable()->index();
            $table->json('launch_scope')->nullable();
            $table->uuid('correlation_id')->nullable()->index();
            $table->string('claim_token', 64)->nullable()->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('lease_expires_at')->nullable()->index();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('failure_code', 120)->nullable()->index();
            $table->text('failure_reason')->nullable();
        });

        // Every row present at this point predates durable launch claims. Preserve
        // provable terminal outcomes and quarantine everything ambiguous.
        DB::table('listing_launch_events')->where('status', 'EXECUTED')->update([
            'operation_status' => 'SUCCEEDED',
        ]);
        DB::table('listing_launch_events')->where('status', 'BLOCKED')->update([
            'operation_status' => 'BLOCKED',
        ]);
        DB::table('listing_launch_events')->where('status', 'FAILED')->update([
            'operation_status' => 'FAILED',
        ]);
        DB::table('listing_launch_events')->where('status', 'CANCELLED')->update([
            'operation_status' => 'CANCELLED',
        ]);
        DB::table('listing_launch_events')->whereNotIn('status', ['EXECUTED', 'BLOCKED', 'FAILED', 'CANCELLED'])->update([
            'operation_status' => 'LEGACY_UNVERIFIED',
            'failure_code' => 'LEGACY_STATE_UNVERIFIED',
            'failure_reason' => 'Pre-durable launch evidence cannot prove this event is safe to execute.',
            'completed_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('listing_launch_events', function (Blueprint $table): void {
            $table->dropColumn([
                'operation_status', 'attempt_count', 'configuration_fingerprint', 'launch_scope',
                'correlation_id', 'claim_token', 'claimed_at', 'lease_expires_at', 'dispatched_at',
                'started_at', 'completed_at', 'last_heartbeat_at', 'failure_code', 'failure_reason',
            ]);
        });
    }
};
