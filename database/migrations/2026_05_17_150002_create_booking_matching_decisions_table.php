<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_matching_decisions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->constrained('bookings')->cascadeOnDelete();

            $table->foreignId('selected_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->unsignedTinyInteger('candidates_count')->default(0);
            $table->decimal('selected_score', 7, 2)->nullable();
            $table->decimal('top_score', 7, 2)->nullable();
            $table->decimal('runner_up_score', 7, 2)->nullable();

            $table->string('algorithm_version', 16)->default('v2');
            $table->string('strategy', 32)->nullable();

            $table->json('weights_snapshot')->nullable();
            $table->json('candidates_breakdown')->nullable();
            $table->json('selected_breakdown')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'created_at']);
            $table->index('selected_user_id');
            $table->index('algorithm_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_matching_decisions');
    }
};
