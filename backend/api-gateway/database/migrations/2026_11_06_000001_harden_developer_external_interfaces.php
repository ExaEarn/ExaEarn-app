<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('developer_webhook_deliveries', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->after('endpoint_id')->constrained('developer_projects')->cascadeOnDelete();
            $table->string('environment', 20)->default('sandbox')->after('project_id')->index();
            $table->uuid('claim_token')->nullable()->after('status')->unique();
            $table->timestamp('claimed_at')->nullable()->after('claim_token')->index();
            $table->timestamp('claim_expires_at')->nullable()->after('claimed_at')->index();
            $table->index(['project_id','environment','status','next_attempt_at'],'developer_webhook_delivery_scope_due');
        });
        DB::table('developer_webhook_endpoints')->whereNull('environment')->orWhereNotIn('environment',['sandbox','production'])->update(['environment'=>'sandbox','status'=>'disabled']);
        foreach (DB::table('developer_webhook_deliveries')->get(['id','endpoint_id']) as $delivery) {
            $endpoint=DB::table('developer_webhook_endpoints')->where('id',$delivery->endpoint_id)->first(['project_id','environment']);
            if($endpoint) DB::table('developer_webhook_deliveries')->where('id',$delivery->id)->update(['project_id'=>$endpoint->project_id,'environment'=>$endpoint->environment ?: 'sandbox']);
        }
    }

    public function down(): void
    {
        Schema::table('developer_webhook_deliveries', function (Blueprint $table): void {
            $table->dropIndex('developer_webhook_delivery_scope_due');
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn(['environment','claim_token','claimed_at','claim_expires_at']);
        });
    }
};
