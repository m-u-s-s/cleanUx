<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_quality_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('reviewer_role')->default('client'); // client, admin, system
            $table->string('final_status')->default('satisfied'); // satisfied, problem_reported
            $table->unsignedTinyInteger('score')->nullable();
            $table->unsignedTinyInteger('cleanliness_score')->nullable();
            $table->unsignedTinyInteger('punctuality_score')->nullable();
            $table->unsignedTinyInteger('behavior_score')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['mission_id', 'reviewer_role']);
            $table->index(['mission_id', 'final_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_quality_reviews');
    }
};