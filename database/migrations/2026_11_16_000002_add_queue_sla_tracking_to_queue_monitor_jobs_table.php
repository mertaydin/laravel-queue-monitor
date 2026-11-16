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

        if (! Schema::connection($connection)->hasTable($tableName)) {
            return;
        }

        Schema::connection($connection)->table($tableName, function (Blueprint $table): void {
            $table->timestamp('queued_at')->nullable();
            $table->unsignedBigInteger('wait_duration_ms')->nullable();
            $table->index(['connection', 'queue', 'queued_at']);
        });
    }

    public function down(): void
    {
        $connection = config('queue-monitor.history.connection');
        $tableName = config('queue-monitor.history.table', 'queue_monitor_jobs');

        if (! Schema::connection($connection)->hasTable($tableName)) {
            return;
        }

        Schema::connection($connection)->table($tableName, function (Blueprint $table): void {
            $table->dropIndex(['connection', 'queue', 'queued_at']);
            $table->dropColumn(['queued_at', 'wait_duration_ms']);
        });
    }
};
