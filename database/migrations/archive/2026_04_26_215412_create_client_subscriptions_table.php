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
        Schema::create('client_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans');

            $table->foreignId('service_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_catalog_id')->nullable()->constrained()->nullOnDelete();

            $table->string('day_of_week'); // monday, tuesday...
            $table->time('heure');

            $table->foreignId('preferred_employee_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('base_price', 10, 2);
            $table->decimal('discounted_price', 10, 2);

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->string('status')->default('active'); // active, paused, cancelled

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_subscriptions');
    }
};
