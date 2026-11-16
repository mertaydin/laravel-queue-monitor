<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('queue-monitor.history.connection'))->create(config('queue-monitor.history.table', 'queue_monitor_jobs'), function (Blueprint $table): void {
            $table->id();
            $table->string('connection', 100);
            $table->string('job_id', 191);
            $table->string('queue', 191)->default('default');
            $table->string('job_name')->nullable();
            $table->string('status', 20)->index();
            $table->longText('payload')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->longText('exception')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->unique(['connection', 'job_id']);
            $table->index(['connection', 'queue', 'status']);
            $table->index(['finished_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection(config('queue-monitor.history.connection'))->dropIfExists(config('queue-monitor.history.table', 'queue_monitor_jobs'));
    }
};
