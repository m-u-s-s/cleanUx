<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_employee_preferences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employe_id')->constrained('users')->cascadeOnDelete();

            $table->boolean('is_favorite')->default(true);

            $table->timestamps();

            $table->unique(['client_id', 'employe_id']);
            $table->index('client_id');
            $table->index('employe_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_employee_preferences');
    }
};