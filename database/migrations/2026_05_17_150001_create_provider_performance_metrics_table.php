<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_performance_metrics', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedSmallInteger('window_days')->default(30);

            $table->unsignedInteger('offers_received')->default(0);
            $table->unsignedInteger('offers_accepted')->default(0);
            $table->unsignedInteger('offers_declined')->default(0);
            $table->unsignedInteger('offers_expired')->default(0);

            $table->unsignedInteger('missions_completed')->default(0);
            $table->unsignedInteger('missions_cancelled_by_provider')->default(0);

            $table->decimal('acceptance_rate', 5, 4)->nullable();
            $table->decimal('completion_rate', 5, 4)->nullable();
            $table->decimal('cancellation_rate', 5, 4)->nullable();

            $table->unsignedInteger('avg_response_seconds')->nullable();

            $table->decimal('rating_avg_window', 3, 2)->nullable();
            $table->unsignedInteger('rating_count_window')->default(0);

            $table->timestamp('computed_at');

            $table->timestamps();

            $table->unique(['user_id', 'period_end'], 'provider_perf_user_period_unique');
            $table->index(['user_id', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_performance_metrics');
    }
};
