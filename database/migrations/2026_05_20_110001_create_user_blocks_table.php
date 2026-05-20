<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['blocker_user_id', 'blocked_user_id']);
            $table->index('blocked_user_id');
        });

        Schema::create('user_reports', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 64);
            // ex: harassment, fraud, inappropriate_content, safety_concern, other
            $table->text('description');
            $table->json('evidence')->nullable();
            $table->enum('status', ['pending', 'under_review', 'resolved_action_taken', 'resolved_no_action', 'dismissed'])->default('pending');
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['reported_user_id', 'status']);
            $table->index(['reporter_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_reports');
        Schema::dropIfExists('user_blocks');
    }
};
