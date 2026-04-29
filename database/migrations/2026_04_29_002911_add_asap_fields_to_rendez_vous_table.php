<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->string('booking_mode')->default('scheduled')->after('booking_channel');
            $table->timestamp('asap_requested_at')->nullable()->after('booking_mode');
            $table->timestamp('asap_deadline_at')->nullable()->after('asap_requested_at');
            $table->timestamp('matched_at')->nullable()->after('asap_deadline_at');
            $table->json('matching_snapshot')->nullable()->after('matched_at');
        });
    }

    public function down(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->dropColumn([
                'booking_mode',
                'asap_requested_at',
                'asap_deadline_at',
                'matched_at',
                'matching_snapshot',
            ]);
        });
    }
};
