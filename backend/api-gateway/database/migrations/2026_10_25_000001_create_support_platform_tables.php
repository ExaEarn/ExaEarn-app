<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_queues', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->json('products')->nullable();
            $table->json('categories')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('support_sla_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('priority');
            $table->string('queue_code')->nullable()->index();
            $table->unsignedInteger('first_response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->boolean('pause_waiting_for_user')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('subcategory')->nullable();
            $table->string('priority')->default('NORMAL')->index();
            $table->string('severity')->default('NORMAL')->index();
            $table->string('status')->default('OPEN')->index();
            $table->string('subject');
            $table->text('description');
            $table->string('source')->default('WEB');
            $table->string('product')->nullable()->index();
            $table->string('related_entity_type')->nullable()->index();
            $table->string('related_entity_id')->nullable()->index();
            $table->foreignId('assigned_team_id')->nullable()->constrained('support_queues')->nullOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('sla_policy_id')->nullable()->constrained('support_sla_policies')->nullOnDelete();
            $table->timestamp('first_response_due_at')->nullable()->index();
            $table->timestamp('resolution_due_at')->nullable()->index();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->string('resolution_code')->nullable();
            $table->json('metadata')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['product', 'category', 'priority']);
        });

        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('sender_type');
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('message_type')->default('MESSAGE');
            $table->string('visibility')->default('PUBLIC');
            $table->text('body');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'visibility', 'created_at']);
        });

        Schema::create('support_ticket_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('support_ticket_messages')->nullOnDelete();
            $table->string('uploaded_by_type');
            $table->unsignedBigInteger('uploaded_by_id');
            $table->string('original_filename');
            $table->string('safe_mime');
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_disk')->default('local');
            $table->string('storage_path');
            $table->string('scan_status')->default('PENDING');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'scan_status']);
        });

        Schema::create('support_escalations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('from_queue')->nullable();
            $table->string('to_queue');
            $table->foreignId('actor_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('reason');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['to_queue', 'created_at']);
        });

        Schema::create('kb_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('kb_articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('kb_categories')->nullOnDelete();
            $table->string('slug');
            $table->string('locale', 12)->default('en');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('status')->default('DRAFT')->index();
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['slug', 'locale']);
        });

        Schema::create('kb_article_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('body');
            $table->json('keywords')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['article_id', 'version']);
        });

        Schema::create('support_chats', function (Blueprint $table): void {
            $table->id();
            $table->string('chat_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
            $table->string('status')->default('OFFLINE')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('support_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_id')->constrained('support_chats')->cascadeOnDelete();
            $table->string('sender_type');
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->text('body');
            $table->unsignedBigInteger('sequence');
            $table->timestamps();
            $table->unique(['chat_id', 'sequence']);
        });

        Schema::create('support_ticket_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('support_csat_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'support_csat_responses',
            'support_ticket_events',
            'support_chat_messages',
            'support_chats',
            'kb_article_versions',
            'kb_articles',
            'kb_categories',
            'support_escalations',
            'support_ticket_attachments',
            'support_ticket_messages',
            'support_tickets',
            'support_sla_policies',
            'support_queues',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
