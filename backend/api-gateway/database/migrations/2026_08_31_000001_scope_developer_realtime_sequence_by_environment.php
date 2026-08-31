<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('developer_realtime_events', function (Blueprint $table): void {
            $table->dropUnique(['project_id', 'stream', 'sequence']);
            $table->unique(
                ['project_id', 'environment', 'stream', 'sequence'],
                'developer_realtime_events_scope_sequence_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('developer_realtime_events', function (Blueprint $table): void {
            $table->dropUnique('developer_realtime_events_scope_sequence_unique');
            $table->unique(['project_id', 'stream', 'sequence']);
        });
    }
};
