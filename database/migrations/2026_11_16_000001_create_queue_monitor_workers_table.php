<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('queue-monitor.workers.connection'))->create('queue_monitor_workers', function (Blueprint $table): void {
            $table->id();
            $table->string('worker_key', 191)->unique();
            $table->string('hostname', 191);
            $table->unsignedBigInteger('process_id');
            $table->string('connection', 100)->index();
            $table->string('queue', 191)->nullable();
            $table->string('status', 20)->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable()->index();
            $table->timestamp('last_job_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();

            $table->index(['connection', 'queue', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection(config('queue-monitor.workers.connection'))->dropIfExists('queue_monitor_workers');
    }
};
