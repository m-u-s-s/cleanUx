<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('report_number')->unique();
            $table->string('status')->default('generated');
            $table->timestamp('generated_at')->nullable();
            $table->string('summary')->nullable();
            $table->unsignedTinyInteger('checklist_completion_rate')->default(0);
            $table->unsignedInteger('before_photos_count')->default(0);
            $table->unsignedInteger('after_photos_count')->default(0);
            $table->unsignedInteger('incident_count')->default(0);
            $table->string('client_validation')->nullable();
            $table->unsignedTinyInteger('quality_score')->nullable();
            $table->json('report_payload')->nullable();
            $table->string('pdf_path')->nullable();

            $table->timestamps();

            $table->index(['mission_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_reports');
    }
};