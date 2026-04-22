<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->foreignId('service_catalog_id')->nullable()->constrained('service_catalogs')->nullOnDelete();
            $table->string('template_name')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('completion_rate')->default(0);
            $table->timestamps();

            $table->index(['mission_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_checklists');
    }
};