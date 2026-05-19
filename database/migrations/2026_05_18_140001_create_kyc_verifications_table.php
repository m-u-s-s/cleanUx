<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_verifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->string('provider', 32);           // mock|onfido|veriff|sumsub
            $table->string('external_applicant_id', 128)->nullable();
            $table->string('external_check_id', 128)->nullable();

            $table->enum('status', [
                'pending',
                'in_review',
                'awaiting_documents',
                'clear',
                'consider',
                'unidentified',
                'rejected',
                'expired',
                'cancelled',
            ])->default('pending');

            $table->enum('decision', ['pending', 'approved', 'rejected', 'manual_review'])
                ->default('pending');

            $table->decimal('score', 4, 2)->nullable();

            $table->string('country_code', 4)->nullable();
            $table->json('requested_checks')->nullable();
            $table->json('result_summary')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('reviewed_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['provider', 'external_applicant_id']);
            $table->index('external_check_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_verifications');
    }
};
