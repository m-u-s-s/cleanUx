<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nps_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('survey_code', 64);   // ex: post_booking, monthly, annual
            $table->unsignedTinyInteger('score');   // 0-10
            $table->enum('category', ['detractor', 'passive', 'promoter']);
            $table->text('comment')->nullable();
            $table->string('locale', 8)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('responded_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'survey_code']);
            $table->index(['survey_code', 'created_at']);
            $table->index(['category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nps_responses');
    }
};
