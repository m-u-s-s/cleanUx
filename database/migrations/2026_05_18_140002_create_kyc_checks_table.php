<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_checks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kyc_verification_id')
                ->constrained('kyc_verifications')->cascadeOnDelete();

            $table->string('check_type', 64);   // document|facial_similarity|watchlist_aml|criminal_record|right_to_work
            $table->enum('result', [
                'pending',
                'clear',
                'consider',
                'rejected',
                'unidentified',
                'caution',
            ])->default('pending');

            $table->string('sub_result', 64)->nullable();
            $table->string('external_id', 128)->nullable();

            $table->decimal('confidence', 4, 2)->nullable();
            $table->json('breakdown')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('checked_at')->nullable();

            $table->timestamps();

            $table->index(['kyc_verification_id', 'check_type']);
            $table->index(['result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_checks');
    }
};
