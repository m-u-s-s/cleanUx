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
        Schema::table('missions', function (Blueprint $table) {
            $table->decimal('employee_cost', 10, 2)->nullable();
            $table->decimal('client_price', 10, 2)->nullable();
            $table->decimal('margin', 10, 2)->nullable();
            $table->integer('actual_duration_minutes')->nullable();
            $table->integer('travel_duration_minutes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            //
        });
    }
};
