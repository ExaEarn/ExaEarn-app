<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_chats', function (Blueprint $table): void {
            $table->string('conversation_number')->nullable()->unique()->after('chat_uuid');
            $table->foreignId('queue_id')->nullable()->after('ticket_id')->constrained('support_queues')->nullOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->after('queue_id')->constrained('admins')->nullOnDelete();
            $table->string('priority')->default('NORMAL')->after('status');
            $table->string('source')->default('WEB')->after('priority');
            $table->string('product')->nullable()->after('source');
            $table->string('related_entity_type')->nullable()->after('product');
            $table->string('related_entity_id')->nullable()->after('related_entity_type');
            $table->timestamp('started_at')->nullable()->after('related_entity_id');
            $table->timestamp('waiting_since')->nullable()->after('started_at');
            $table->timestamp('assigned_at')->nullable()->after('waiting_since');
            $table->timestamp('first_agent_response_at')->nullable()->after('assigned_at');
            $table->timestamp('ended_at')->nullable()->after('first_agent_response_at');
            $table->foreignId('converted_ticket_id')->nullable()->after('ended_at')->constrained('support_tickets')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable()->after('converted_ticket_id');
            $table->index(['status', 'queue_id', 'waiting_since']);
            $table->index(['assigned_agent_id', 'status']);
        });

        Schema::table('support_chat_messages', function (Blueprint $table): void {
            $table->string('message_uuid')->nullable()->unique()->after('id');
            $table->string('message_type')->default('MESSAGE')->after('sender_id');
            $table->string('visibility')->default('PUBLIC')->after('message_type');
            $table->json('metadata')->nullable()->after('body');
            $table->string('idempotency_key')->nullable()->after('metadata');
            $table->timestamp('delivered_at')->nullable()->after('created_at');
            $table->timestamp('read_at')->nullable()->after('delivered_at');
            $table->unique(['chat_id', 'idempotency_key']);
        });

        Schema::create('support_live_chat_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('support_agent_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->unique()->constrained('admins')->cascadeOnDelete();
            $table->boolean('support_enabled')->default(false);
            $table->foreignId('default_queue_id')->nullable()->constrained('support_queues')->nullOnDelete();
            $table->unsignedInteger('max_concurrent_chats')->default(2);
            $table->string('status')->default('OFFLINE')->index();
            $table->timestamp('last_heartbeat_at')->nullable()->index();
            $table->timestamp('last_assigned_at')->nullable();
            $table->json('skills')->nullable();
            $table->timestamps();
        });

        Schema::create('support_canned_responses', function (Blueprint $table): void {
            $table->id();
            $table->string('category')->default('general')->index();
            $table->string('title');
            $table->text('body');
            $table->string('status')->default('ACTIVE')->index();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_canned_responses');
        Schema::dropIfExists('support_agent_profiles');
        Schema::dropIfExists('support_live_chat_settings');

        Schema::table('support_chat_messages', function (Blueprint $table): void {
            $table->dropUnique(['message_uuid']);
            $table->dropUnique(['chat_id', 'idempotency_key']);
            $table->dropColumn(['message_uuid', 'message_type', 'visibility', 'metadata', 'idempotency_key', 'delivered_at', 'read_at']);
        });

        Schema::table('support_chats', function (Blueprint $table): void {
            $table->dropIndex(['status', 'queue_id', 'waiting_since']);
            $table->dropIndex(['assigned_agent_id', 'status']);
            $table->dropForeign(['queue_id']);
            $table->dropForeign(['assigned_agent_id']);
            $table->dropForeign(['converted_ticket_id']);
            $table->dropUnique(['conversation_number']);
            $table->dropColumn([
                'conversation_number',
                'queue_id',
                'assigned_agent_id',
                'priority',
                'source',
                'product',
                'related_entity_type',
                'related_entity_id',
                'started_at',
                'waiting_since',
                'assigned_at',
                'first_agent_response_at',
                'ended_at',
                'converted_ticket_id',
                'last_activity_at',
            ]);
        });
    }
};
