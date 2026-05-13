<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'contact_phone')) {
                $table->string('contact_phone')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'estimated_price')) {
                $table->decimal('estimated_price', 10, 2)->nullable();
            }

            if (! Schema::hasColumn('bookings', 'estimated_duration_minutes')) {
                $table->unsignedInteger('estimated_duration_minutes')->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};
