<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flag_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('flag_key')->unique();
            $table->boolean('is_enabled');
            $table->json('override_config')->nullable(); // percentage / users / roles arrays
            $table->string('reason')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flag_overrides');
    }
};
