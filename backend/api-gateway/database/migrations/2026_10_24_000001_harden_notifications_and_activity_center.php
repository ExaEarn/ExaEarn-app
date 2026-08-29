<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            if (!Schema::hasColumn('notifications', 'event_key')) {
                $table->string('event_key', 160)->nullable()->after('type');
            }
            if (!Schema::hasColumn('notifications', 'event_id')) {
                $table->uuid('event_id')->nullable()->after('event_key');
            }
            if (!Schema::hasColumn('notifications', 'product')) {
                $table->string('product', 60)->nullable()->after('event_id');
            }
            if (!Schema::hasColumn('notifications', 'category')) {
                $table->string('category', 40)->default('TRANSACTIONAL')->after('product');
            }
            if (!Schema::hasColumn('notifications', 'priority')) {
                $table->string('priority', 20)->default('NORMAL')->after('category');
            }
            if (!Schema::hasColumn('notifications', 'severity')) {
                $table->string('severity', 20)->default('NORMAL')->after('priority');
            }
            if (!Schema::hasColumn('notifications', 'mandatory')) {
                $table->boolean('mandatory')->default(false)->after('severity');
            }
            if (!Schema::hasColumn('notifications', 'template_key')) {
                $table->string('template_key', 120)->nullable()->after('mandatory');
            }
            if (!Schema::hasColumn('notifications', 'template_version')) {
                $table->unsignedInteger('template_version')->default(1)->after('template_key');
            }
            if (!Schema::hasColumn('notifications', 'deep_link')) {
                $table->string('deep_link', 240)->nullable()->after('template_version');
            }
            if (!Schema::hasColumn('notifications', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('read_at');
            }
            if (!Schema::hasColumn('notifications', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('archived_at');
            }
        });

        Schema::table('notification_logs', function (Blueprint $table): void {
            if (!Schema::hasColumn('notification_logs', 'event_id')) {
                $table->uuid('event_id')->nullable()->after('notification_id');
            }
            if (!Schema::hasColumn('notification_logs', 'recipient')) {
                $table->string('recipient', 190)->nullable()->after('provider');
            }
            if (!Schema::hasColumn('notification_logs', 'attempt_number')) {
                $table->unsignedInteger('attempt_number')->default(1)->after('recipient');
            }
            if (!Schema::hasColumn('notification_logs', 'status')) {
                $table->string('status', 40)->default('QUEUED')->after('attempt_number');
            }
            if (!Schema::hasColumn('notification_logs', 'provider_message_id')) {
                $table->string('provider_message_id', 190)->nullable()->after('status');
            }
            if (!Schema::hasColumn('notification_logs', 'queued_at')) {
                $table->timestamp('queued_at')->nullable()->after('provider_message_id');
            }
            if (!Schema::hasColumn('notification_logs', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('queued_at');
            }
            if (!Schema::hasColumn('notification_logs', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('sent_at');
            }
            if (!Schema::hasColumn('notification_logs', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('delivered_at');
            }
            if (!Schema::hasColumn('notification_logs', 'error_code')) {
                $table->string('error_code', 80)->nullable()->after('failed_at');
            }
            if (!Schema::hasColumn('notification_logs', 'safe_error')) {
                $table->string('safe_error', 240)->nullable()->after('error_code');
            }
            if (!Schema::hasColumn('notification_logs', 'template_version')) {
                $table->unsignedInteger('template_version')->nullable()->after('safe_error');
            }
        });

        Schema::create('notification_event_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 140)->unique();
            $table->string('product', 60)->index();
            $table->string('category', 40)->index();
            $table->string('priority', 20)->default('NORMAL');
            $table->string('severity', 20)->default('NORMAL');
            $table->json('default_channels');
            $table->boolean('user_configurable')->default(true);
            $table->boolean('mandatory')->default(false);
            $table->string('template_key', 120);
            $table->unsignedInteger('template_version')->default(1);
            $table->string('dedup_strategy', 40)->default('EVENT_RECIPIENT_CHANNEL');
            $table->string('retention_policy', 40)->default('STANDARD');
            $table->string('deep_link_policy', 40)->default('SAFE_ROUTE');
            $table->boolean('activity_eligible')->default(true);
            $table->string('status', 40)->default('ACTIVE')->index();
            $table->timestamps();
        });

        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('template_key', 120);
            $table->unsignedInteger('version')->default(1);
            $table->string('channel', 24);
            $table->string('locale', 12)->default('en');
            $table->string('title', 180);
            $table->text('body');
            $table->json('variables')->nullable();
            $table->string('status', 40)->default('ACTIVE')->index();
            $table->timestamp('effective_at')->nullable();
            $table->timestamps();
            $table->unique(['template_key', 'version', 'channel', 'locale'], 'notification_template_version_locale');
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 80);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);
            $table->boolean('marketing_consent')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'scope']);
        });

        Schema::create('notification_provider_health', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 80)->unique();
            $table->string('channel', 24);
            $table->string('status', 40)->default('UNKNOWN')->index();
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_provider_health');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_event_definitions');
    }
};
