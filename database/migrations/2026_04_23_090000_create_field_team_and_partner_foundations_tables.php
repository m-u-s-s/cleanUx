<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('slug')->unique();
            $table->string('status')->default('active');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('billing_email')->nullable();
            $table->decimal('quality_score', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('field_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_lead_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active');
            $table->boolean('is_internal')->default(true);
            $table->unsignedInteger('max_concurrent_missions')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('field_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role_on_team')->default('agent');
            $table->boolean('is_team_lead')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['field_team_id', 'user_id']);
        });

        Schema::create('partner_zone_coverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_catalog_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coverage_status')->default('active');
            $table->unsignedInteger('priority')->default(1);
            $table->unsignedInteger('max_daily_capacity')->nullable();
            $table->unsignedInteger('sla_response_hours')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('mission_team_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_assignment_id')->nullable()->constrained('mission_assignments')->nullOnDelete();
            $table->string('assignment_status')->default('assigned');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('instructions_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['mission_id', 'field_team_id']);
        });

        Schema::create('mission_partner_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_partner_id')->constrained()->cascadeOnDelete();
            $table->string('assignment_status')->default('assigned');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('agreed_rate', 10, 2)->nullable();
            $table->json('sla_snapshot')->nullable();
            $table->json('instructions_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['mission_id', 'service_partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_partner_assignments');
        Schema::dropIfExists('mission_team_assignments');
        Schema::dropIfExists('partner_zone_coverages');
        Schema::dropIfExists('field_team_members');
        Schema::dropIfExists('field_teams');
        Schema::dropIfExists('service_partners');
    }
};
