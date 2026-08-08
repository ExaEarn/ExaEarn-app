<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'profile_image_url')) {
                $table->string('profile_image_url', 512)->nullable()->after('preferences');
            }
            if (!Schema::hasColumn('users', 'profile_thumbnail_url')) {
                $table->string('profile_thumbnail_url', 512)->nullable()->after('profile_image_url');
            }
            if (!Schema::hasColumn('users', 'avatar_id')) {
                $table->string('avatar_id', 80)->nullable()->after('profile_thumbnail_url');
            }
            if (!Schema::hasColumn('users', 'profile_display_type')) {
                $table->string('profile_display_type', 32)->default('initials')->after('avatar_id');
            }
            if (!Schema::hasColumn('users', 'profile_visibility')) {
                $table->string('profile_visibility', 32)->default('self')->after('profile_display_type');
            }
            if (!Schema::hasColumn('users', 'profile_image_status')) {
                $table->string('profile_image_status', 32)->default('none')->after('profile_visibility');
            }
            if (!Schema::hasColumn('users', 'profile_image_updated_at')) {
                $table->timestamp('profile_image_updated_at')->nullable()->after('profile_image_status');
            }
            if (!Schema::hasColumn('users', 'profile_image_privileges_suspended_until')) {
                $table->timestamp('profile_image_privileges_suspended_until')->nullable()->after('profile_image_updated_at');
            }
            if (!Schema::hasColumn('users', 'profile_image_moderation_note')) {
                $table->string('profile_image_moderation_note', 500)->nullable()->after('profile_image_privileges_suspended_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'profile_image_moderation_note',
                'profile_image_privileges_suspended_until',
                'profile_image_updated_at',
                'profile_image_status',
                'profile_visibility',
                'profile_display_type',
                'avatar_id',
                'profile_thumbnail_url',
                'profile_image_url',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};