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
        Schema::table('markets', function (Blueprint $table): void {
            $table->string('governance_source', 32)->default('UNKNOWN_REVIEW_REQUIRED')->after('symbol')->index();
            $table->foreignId('listing_application_id')->nullable()->after('governance_source')->constrained('listing_applications')->nullOnDelete();
            $table->foreignId('listing_launch_event_id')->nullable()->after('listing_application_id')->constrained('listing_launch_events')->nullOnDelete();
            $table->timestamp('activated_at')->nullable()->after('listing_launch_event_id');
        });

        // Preserve observed live state, but do not invent authority for unknown legacy rows.
        DB::table('markets')->whereIn(DB::raw('lower(status)'), ['active', 'trading'])->update(['activated_at' => now()]);
        DB::table('listing_market_configurations')->whereNotNull('market_id')->orderBy('id')->each(function (object $configuration): void {
            DB::table('markets')->where('id', $configuration->market_id)->update([
                'governance_source' => 'LISTING_GOVERNED', 'listing_application_id' => $configuration->application_id,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('listing_launch_event_id');
            $table->dropConstrainedForeignId('listing_application_id');
            $table->dropIndex(['governance_source']);
            $table->dropColumn(['governance_source', 'activated_at']);
        });
    }
};
