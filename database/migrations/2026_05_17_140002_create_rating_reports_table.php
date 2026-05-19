<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('feedback_id')
                ->constrained('feedback')->cascadeOnDelete();

            $table->foreignId('reporter_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->enum('reason', [
                'spam',
                'offensive',
                'fake',
                'irrelevant',
                'discloses_personal_info',
                'harassment',
                'other',
            ])->default('other');

            $table->text('details')->nullable();

            $table->enum('status', ['pending', 'reviewed_kept', 'reviewed_hidden', 'dismissed'])
                ->default('pending');

            $table->foreignId('reviewed_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_note')->nullable();

            $table->timestamps();

            $table->unique(['feedback_id', 'reporter_user_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_reports');
    }
};
