<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trade_zone_settings')) {
            return;
        }

        Schema::create('trade_zone_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_id')->constrained('trades')->cascadeOnDelete();
            $table->foreignId('service_zone_id')->constrained('service_zones')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->decimal('price_multiplier', 5, 2)->default(1.00);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['trade_id', 'service_zone_id']);
            $table->index(['service_zone_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_zone_settings');
    }
};
