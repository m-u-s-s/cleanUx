<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Schema-drift repair: create the `client_subscriptions` table. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_subscriptions')) {
            Schema::create('client_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->unsignedBigInteger('plan_id')->nullable()->index();
                $table->unsignedBigInteger('service_zone_id')->nullable()->index();
                $table->unsignedBigInteger('service_catalog_id')->nullable()->index();
                $table->string('day_of_week')->nullable();
                $table->string('heure')->nullable();
                $table->unsignedBigInteger('preferred_employee_id')->nullable()->index();
                $table->decimal('base_price', 10, 2)->nullable();
                $table->decimal('discounted_price', 10, 2)->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('status')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_subscriptions');
    }
};
