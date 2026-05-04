<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('employe_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('organization_account_id')->nullable()->constrained('organization_accounts')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('incident');
            $table->string('priority')->default('normale');
            $table->string('status')->default('ouvert');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location_notes')->nullable();
            $table->json('photos')->nullable();
            $table->json('meta')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('complaint_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('organization_account_id')->nullable()->constrained('organization_accounts')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category')->default('reclamation');
            $table->string('priority')->default('normale');
            $table->string('status')->default('ouvert');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->json('attachments')->nullable();
            $table->text('admin_response')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['category', 'created_at']);
        });

        Schema::create('quality_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('employe_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_zone_id')->nullable()->constrained('service_zones')->nullOnDelete();
            $table->foreignId('auditor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->unsignedTinyInteger('punctuality_score')->default(0);
            $table->unsignedTinyInteger('service_score')->default(0);
            $table->unsignedTinyInteger('communication_score')->default(0);
            $table->json('checklist')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->string('status')->default('brouillon');
            $table->timestamp('audited_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'audited_at']);
            $table->index(['service_zone_id', 'employe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_audits');
        Schema::dropIfExists('complaint_cases');
        Schema::dropIfExists('incident_reports');
    }
};
