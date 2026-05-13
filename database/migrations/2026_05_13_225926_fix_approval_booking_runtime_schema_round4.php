<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->fixEnterpriseBookingApprovals();
        $this->fixBookingsRuntimeColumns();
    }

    private function fixEnterpriseBookingApprovals(): void
    {
        if (! Schema::hasTable('enterprise_booking_approvals')) {
            return;
        }

        Schema::table('enterprise_booking_approvals', function (Blueprint $table) {
            if (! Schema::hasColumn('enterprise_booking_approvals', 'manager_approved_by_user_id')) {
                $table->unsignedBigInteger('manager_approved_by_user_id')->nullable()->index();
            }

            if (! Schema::hasColumn('enterprise_booking_approvals', 'manager_approved_at')) {
                $table->timestamp('manager_approved_at')->nullable();
            }

            if (! Schema::hasColumn('enterprise_booking_approvals', 'manager_note')) {
                $table->text('manager_note')->nullable();
            }

            if (! Schema::hasColumn('enterprise_booking_approvals', 'finance_approved_by_user_id')) {
                $table->unsignedBigInteger('finance_approved_by_user_id')->nullable()->index();
            }

            if (! Schema::hasColumn('enterprise_booking_approvals', 'finance_approved_at')) {
                $table->timestamp('finance_approved_at')->nullable();
            }

            if (! Schema::hasColumn('enterprise_booking_approvals', 'finance_note')) {
                $table->text('finance_note')->nullable();
            }

            if (! Schema::hasColumn('enterprise_booking_approvals', 'final_approved_by_user_id')) {
                $table->unsignedBigInteger('final_approved_by_user_id')->nullable()->index();
            }

            if (! Schema::hasColumn('enterprise_booking_approvals', 'final_approved_at')) {
                $table->timestamp('final_approved_at')->nullable();
            }

            if (! Schema::hasColumn('enterprise_booking_approvals', 'rejected_by_user_id')) {
                $table->unsignedBigInteger('rejected_by_user_id')->nullable()->index();
            }

            if (! Schema::hasColumn('enterprise_booking_approvals', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
        });
    }

    private function fixBookingsRuntimeColumns(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'asap_requested_at')) {
                $table->timestamp('asap_requested_at')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'asap_deadline_at')) {
                $table->timestamp('asap_deadline_at')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'matched_at')) {
                $table->timestamp('matched_at')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'matching_snapshot')) {
                $table->json('matching_snapshot')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'destination_lat')) {
                $table->decimal('destination_lat', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('bookings', 'destination_lng')) {
                $table->decimal('destination_lng', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('bookings', 'address_components')) {
                $table->json('address_components')->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};