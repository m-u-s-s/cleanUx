<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispute_resolutions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('complaint_case_id')
                ->constrained('complaint_cases')->cascadeOnDelete();

            $table->enum('resolution_type', [
                'refund_full',
                'refund_partial',
                'credit',
                'promo_code',
                'replacement_booking',
                'provider_warning',
                'provider_sanction',
                'no_action',
                'dismissed',
                'other',
            ]);

            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');

            $table->text('explanation')->nullable();

            $table->foreignId('issued_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->enum('status', ['proposed', 'applied', 'failed', 'reversed'])
                ->default('proposed');

            $table->string('external_ref', 128)->nullable();
            $table->string('stripe_refund_id', 128)->nullable();
            $table->foreignId('promo_code_id')->nullable()
                ->constrained('promo_codes')->nullOnDelete();
            $table->foreignId('replacement_booking_id')->nullable()
                ->constrained('bookings')->nullOnDelete();

            $table->timestamp('applied_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['complaint_case_id', 'status']);
            $table->index('resolution_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_resolutions');
    }
};
