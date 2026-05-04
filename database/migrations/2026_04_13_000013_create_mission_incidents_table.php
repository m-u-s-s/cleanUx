<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('incident_type')->default('general');
            $table->string('severity')->default('medium'); // low, medium, high, critical
            $table->string('status')->default('open'); // open, in_review, resolved, dismissed
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->boolean('client_visible')->default(true);
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['mission_id', 'status']);
            $table->index(['mission_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_incidents');
    }
};