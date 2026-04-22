<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('role_on_mission')->default('member'); // lead, member
            $table->string('assignment_status')->default('assigned'); // assigned, accepted, declined, arrived, completed

            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('declined_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->timestamps();

            $table->index('role_on_mission');
            $table->index('assignment_status');
            $table->index(['mission_id', 'assignment_status']);
            $table->unique(['mission_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_assignments');
    }
};