<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->default('core');
            $table->enum('rollout_strategy', ['global', 'role', 'plan', 'zone', 'organization'])->default('global');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->json('settings')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();

            $table->index(['category', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_modules');
    }
};
