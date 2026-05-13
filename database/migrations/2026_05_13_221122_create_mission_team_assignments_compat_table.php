<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mission_team_assignments')) {
            return;
        }

        Schema::create('mission_team_assignments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('mission_id')->index();
            $table->unsignedBigInteger('field_team_id')->index();

            $table->string('assignment_status')->default('assigned');
            $table->string('status')->nullable();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedBigInteger('assigned_by_user_id')->nullable();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['mission_id', 'field_team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_team_assignments');
    }
};