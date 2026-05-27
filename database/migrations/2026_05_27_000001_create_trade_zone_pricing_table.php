<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trade_zone_pricing')) {
            return;
        }

        Schema::create('trade_zone_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_id')->constrained('trades')->cascadeOnDelete();
            $table->foreignId('service_zone_id')->constrained('service_zones')->cascadeOnDelete();
            $table->unsignedInteger('base_rate_cents')->default(0);
            $table->decimal('surge_multiplier', 5, 2)->default(1.00);
            $table->unsignedInteger('min_price_cents')->nullable();
            $table->unsignedInteger('max_price_cents')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['trade_id', 'service_zone_id']);
            $table->index(['service_zone_id', 'is_active']);
            $table->index(['trade_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_zone_pricing');
    }
};
