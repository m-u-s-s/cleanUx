<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enterprise_booking_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('organization_account_id')->nullable()->constrained('organization_accounts')->nullOnDelete();
            $table->foreignId('organization_site_id')->nullable()->constrained('organization_sites')->nullOnDelete();

            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finance_approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('pending_manager');
            // pending_manager, pending_finance, approved, rejected, cancelled

            $table->text('request_note')->nullable();
            $table->text('manager_note')->nullable();
            $table->text('finance_note')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamp('manager_approved_at')->nullable();
            $table->timestamp('finance_approved_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enterprise_booking_approvals');
    }
};