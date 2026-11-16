<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('queue-monitor.history.connection');
        $tableName = config('queue-monitor.history.table', 'queue_monitor_jobs');
        $schema = Schema::connection($connection);

        if (! $schema->hasTable($tableName)) {
            return;
        }

        $schema->table($tableName, function (Blueprint $table): void {
            $table->string('batch_id', 191)->nullable()->index();
            $table->unsignedInteger('chain_remaining')->default(0);
        });
    }

    public function down(): void
    {
        $connection = config('queue-monitor.history.connection');
        $tableName = config('queue-monitor.history.table', 'queue_monitor_jobs');
        $schema = Schema::connection($connection);

        if (! $schema->hasTable($tableName)) {
            return;
        }

        $schema->table($tableName, function (Blueprint $table): void {
            $table->dropIndex(['batch_id']);
            $table->dropColumn(['batch_id', 'chain_remaining']);
        });
    }
};
