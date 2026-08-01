<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skills_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->string('icon', 80)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('skills_categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table): void {
                if (!Schema::hasColumn('courses', 'category_id')) {
                    $table->foreignId('category_id')->nullable()->after('created_by')->constrained('skills_categories')->nullOnDelete();
                }
                if (!Schema::hasColumn('courses', 'slug')) {
                    $table->string('slug', 180)->nullable()->unique()->after('title');
                }
                if (!Schema::hasColumn('courses', 'instructor_name')) {
                    $table->string('instructor_name', 120)->nullable()->after('slug');
                }
                if (!Schema::hasColumn('courses', 'thumbnail_url')) {
                    $table->string('thumbnail_url', 255)->nullable()->after('description');
                }
                if (!Schema::hasColumn('courses', 'language')) {
                    $table->string('language', 40)->default('English')->after('difficulty');
                }
                if (!Schema::hasColumn('courses', 'price')) {
                    $table->decimal('price', 20, 8)->default(0)->after('duration');
                }
                if (!Schema::hasColumn('courses', 'settlement_asset')) {
                    $table->string('settlement_asset', 20)->default('USDT')->after('price');
                }
                if (!Schema::hasColumn('courses', 'credential_available')) {
                    $table->boolean('credential_available')->default(false)->after('status');
                }
                if (!Schema::hasColumn('courses', 'published_at')) {
                    $table->timestamp('published_at')->nullable()->after('credential_available');
                }
            });
        }

        Schema::create('instructor_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name', 120);
            $table->string('headline', 180)->nullable();
            $table->text('bio')->nullable();
            $table->json('expertise')->nullable();
            $table->json('portfolio_links')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique('user_id');
            $table->index('status');
        });

        Schema::create('skills_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('credential_code', 80)->unique();
            $table->string('title', 180);
            $table->json('skills')->nullable();
            $table->string('status', 32)->default('verified');
            $table->timestamp('issued_at')->nullable();
            $table->string('verification_hash', 100)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('skills_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->string('sponsor_name', 160)->nullable();
            $table->text('description');
            $table->json('required_skills')->nullable();
            $table->decimal('reward_amount', 20, 8)->default(0);
            $table->string('reward_asset', 20)->default('USDT');
            $table->string('difficulty', 40)->default('intermediate');
            $table->string('status', 40)->default('draft');
            $table->timestamp('deadline_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'deadline_at']);
        });

        Schema::create('skills_challenge_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('challenge_id')->constrained('skills_challenges')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->string('repository_url', 255)->nullable();
            $table->string('demo_url', 255)->nullable();
            $table->json('attachments')->nullable();
            $table->string('status', 40)->default('submitted');
            $table->timestamps();
            $table->unique(['challenge_id', 'user_id']);
        });

        Schema::create('skills_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('company_name', 160);
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->string('type', 40)->default('freelance');
            $table->text('description');
            $table->json('required_skills')->nullable();
            $table->string('compensation_label', 120)->nullable();
            $table->string('location_type', 40)->default('remote');
            $table->string('status', 40)->default('draft');
            $table->timestamp('deadline_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'type']);
        });

        Schema::create('skills_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('skills_opportunities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('cover_note')->nullable();
            $table->string('portfolio_url', 255)->nullable();
            $table->string('status', 40)->default('submitted');
            $table->timestamps();
            $table->unique(['opportunity_id', 'user_id']);
        });

        Schema::create('skills_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan_code', 40);
            $table->string('status', 40)->default('pending');
            $table->string('billing_cycle', 20)->default('monthly');
            $table->decimal('amount', 20, 8)->default(0);
            $table->string('settlement_asset', 20)->default('USDT');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        $this->seedCategories();
    }

    public function down(): void
    {
        Schema::dropIfExists('skills_subscriptions');
        Schema::dropIfExists('skills_applications');
        Schema::dropIfExists('skills_opportunities');
        Schema::dropIfExists('skills_challenge_submissions');
        Schema::dropIfExists('skills_challenges');
        Schema::dropIfExists('skills_credentials');
        Schema::dropIfExists('instructor_profiles');

        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table): void {
                foreach (['published_at', 'credential_available', 'settlement_asset', 'price', 'language', 'thumbnail_url', 'instructor_name', 'slug', 'category_id'] as $column) {
                    if (Schema::hasColumn('courses', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('skills');
        Schema::dropIfExists('skills_categories');
    }

    private function seedCategories(): void
    {
        $categories = [
            ['Software Development', 'software-development', 'Build modern web, mobile and backend systems.'],
            ['AI & Automation', 'ai-automation', 'Use AI systems, agents and automation responsibly.'],
            ['Blockchain & Web3', 'blockchain-web3', 'Create wallets, smart contracts and Web3 products.'],
            ['Product/UI/UX Design', 'product-ui-ux-design', 'Design usable, commercial digital products.'],
            ['Digital Marketing', 'digital-marketing', 'Grow products with performance and brand channels.'],
            ['Cybersecurity', 'cybersecurity', 'Protect systems, users and financial workflows.'],
            ['Data & Analytics', 'data-analytics', 'Turn data into decisions, dashboards and models.'],
            ['Finance & Market Education', 'finance-market-education', 'Understand markets, risk and financial products.'],
            ['Entrepreneurship', 'entrepreneurship', 'Launch, operate and monetize durable ventures.'],
        ];

        foreach ($categories as [$name, $slug, $description]) {
            DB::table('skills_categories')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
};
