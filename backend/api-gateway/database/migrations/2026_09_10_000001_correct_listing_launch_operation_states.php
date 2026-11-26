<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('listing_launch_events', 'dispatch_attempt_count')) {
            Schema::table('listing_launch_events', function (Blueprint $table): void {
                $table->unsignedInteger('dispatch_attempt_count')->default(0)->after('attempt_count');
            });
        }

        $this->repairExistingRows();
    }

    public function repairExistingRows(): void
    {
        if (! Schema::hasTable('listing_launch_events') || ! Schema::hasColumn('listing_launch_events', 'operation_status')) {
            return;
        }

        foreach ([
            'EXECUTED' => 'SUCCEEDED',
            'BLOCKED' => 'BLOCKED',
            'FAILED' => 'FAILED',
            'CANCELLED' => 'CANCELLED',
        ] as $legacyStatus => $operationStatus) {
            DB::table('listing_launch_events')
                ->where('status', $legacyStatus)
                ->update([
                    'operation_status' => $operationStatus,
                    'lease_expires_at' => null,
                    'claim_token' => null,
                ]);
        }

        // The unsafe migration supplied SCHEDULED as a default. A row lacking all
        // durable scheduling evidence is legacy, not an executable operation.
        DB::table('listing_launch_events')
            ->whereIn('operation_status', [
                'SCHEDULED', 'RETRY_PENDING', 'DISPATCH_PENDING', 'DISPATCHED', 'EXECUTING',
            ])
            ->where(function ($query): void {
                $query->whereNull('configuration_fingerprint')
                    ->orWhereNull('correlation_id')
                    ->orWhereNull('launch_scope');
            })
            ->update([
                'operation_status' => 'LEGACY_UNVERIFIED',
                'failure_code' => 'LEGACY_STATE_UNVERIFIED',
                'failure_reason' => 'Legacy launch evidence cannot prove this event is safe to execute.',
                'completed_at' => now(),
                'lease_expires_at' => null,
                'claim_token' => null,
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('listing_launch_events', 'dispatch_attempt_count')) {
            Schema::table('listing_launch_events', function (Blueprint $table): void {
                $table->dropColumn('dispatch_attempt_count');
            });
        }
    }
};
