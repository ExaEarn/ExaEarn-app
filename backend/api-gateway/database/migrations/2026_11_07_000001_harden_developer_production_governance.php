<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void{
  Schema::table('admins',fn(Blueprint $t)=>$t->foreignId('canonical_user_id')->nullable()->after('id')->constrained('users')->restrictOnDelete()->unique());
  Schema::create('developer_production_capability_reviews',function(Blueprint $t):void{
   $t->id();$t->uuid('review_uuid')->unique();$t->foreignId('request_id')->constrained('developer_production_access_requests')->cascadeOnDelete();$t->foreignId('capability_id')->constrained('developer_production_capabilities')->cascadeOnDelete();
   $t->foreignId('admin_id')->constrained('admins')->restrictOnDelete();$t->foreignId('canonical_user_id')->constrained('users')->restrictOnDelete();$t->string('decision',30);$t->unsignedSmallInteger('review_sequence');$t->string('reason_code',100);$t->text('internal_note')->nullable();$t->string('policy_version',80)->nullable();$t->string('idempotency_key',100);$t->timestamps();
   $t->unique(['capability_id','canonical_user_id','decision'],'developer_capability_distinct_reviewer');$t->unique(['request_id','idempotency_key']);
  });
  Schema::table('developer_production_capabilities',function(Blueprint $t):void{$t->unsignedTinyInteger('required_approvals')->default(1);$t->unsignedTinyInteger('approval_count')->default(0);});
  DB::table('developer_production_capabilities')->where('status','approved')->whereIn('capability',['futures.trade','margin.trade','margin.manage','wallet.transfer','wallet.withdraw','staking.manage','copy.execute','copy.manage','p2p.trade','fiat.deposit','fiat.payout','exapay.refunds','exapay.manage','giftcard.trade','exaai.execute','exaai.manage'])->update(['status'=>'restricted','reason_code'=>'DUAL_REVIEW_REVALIDATION_REQUIRED','required_approvals'=>2,'approval_count'=>0,'decided_by'=>null,'decided_at'=>null]);
 }
 public function down():void{Schema::table('developer_production_capabilities',fn(Blueprint $t)=>$t->dropColumn(['required_approvals','approval_count']));Schema::dropIfExists('developer_production_capability_reviews');Schema::table('admins',fn(Blueprint $t)=>$t->dropConstrainedForeignId('canonical_user_id'));}
};
