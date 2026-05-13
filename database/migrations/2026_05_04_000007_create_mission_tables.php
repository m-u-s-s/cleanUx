<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->nullable()
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->foreignId('provider_organization_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->nullOnDelete();

            $table->foreignId('lead_provider_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('provider_team_id')
                ->nullable()
                ->constrained('provider_teams')
                ->nullOnDelete();

            // planned, assigned, en_route, arrived, started, paused, completed, cancelled.
            $table->string('status')->default('planned');

            $table->timestamp('planned_start_at')->nullable();
            $table->timestamp('actual_start_at')->nullable();
            $table->timestamp('actual_end_at')->nullable();

            $table->decimal('start_lat', 10, 7)->nullable();
            $table->decimal('start_lng', 10, 7)->nullable();
            $table->decimal('end_lat', 10, 7)->nullable();
            $table->decimal('end_lng', 10, 7)->nullable();

            $table->integer('estimated_duration_minutes')->nullable();
            $table->integer('actual_duration_minutes')->nullable();

            $table->decimal('client_price', 10, 2)->nullable();
            $table->decimal('provider_cost', 10, 2)->nullable();
            $table->decimal('platform_commission', 10, 2)->nullable();
            $table->decimal('margin', 10, 2)->nullable();

            $table->string('report_path')->nullable();

            $table->json('quality_snapshot')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['status', 'planned_start_at']);
            $table->index(['provider_organization_id', 'status']);
            $table->index(['lead_provider_user_id', 'status']);
            $table->index('provider_team_id');

            $table->unsignedBigInteger('rendez_vous_id')->nullable();
            $table->unsignedBigInteger('organization_account_id')->nullable();
            $table->unsignedBigInteger('organization_site_id')->nullable();
            $table->unsignedBigInteger('service_catalog_id')->nullable();
            $table->unsignedBigInteger('service_zone_id')->nullable();

            $table->unsignedBigInteger('lead_employee_id')->nullable();

            $table->string('mission_type')->default('standard');

            $table->timestamp('planned_end_at')->nullable();

            $table->decimal('destination_lat', 10, 7)->nullable();
            $table->decimal('destination_lng', 10, 7)->nullable();

            $table->text('notes')->nullable();
        });

        Schema::create('mission_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // lead, worker, support, quality_checker.
            $table->string('role')->default('worker');

            // assigned, accepted, declined, en_route, arrived, completed.
            $table->string('status')->default('assigned');
            $table->string('assignment_status')->default('pending');

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('assigned_at')->nullable();


            $table->timestamps();

            $table->unique(['mission_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('mission_verification_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            // start, end.
            $table->string('type');

            $table->string('code_hash');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();

            $table->foreignId('consumed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['mission_id', 'type']);
        });

        Schema::create('mission_tracking_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // active, stopped.
            $table->string('status')->default('active');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();

            $table->timestamps();

            $table->index(['mission_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('mission_positions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);

            $table->decimal('accuracy', 10, 2)->nullable();
            $table->timestamp('recorded_at')->nullable();

            $table->timestamps();

            $table->index(['mission_id', 'recorded_at']);
        });

        Schema::create('mission_checklists', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('service_catalog_id')->nullable();

            $table->string('title')->nullable();

            // open, completed.
            $table->string('template_name')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('completion_rate')->default(0);

            $table->timestamps();

            $table->index(['mission_id', 'status']);
        });

        Schema::create('mission_checklist_items', function (Blueprint $table) {
            $table->id();

            $table->string('label')->nullable();
            $table->string('item_type')->default('checkbox');

            $table->foreignId('mission_checklist_id')
                ->constrained('mission_checklists')
                ->cascadeOnDelete();


            $table->string('title')->nullable();
            $table->boolean('is_required')->default(false);

            // todo, done.
            $table->string('status')->default('todo');

            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['mission_checklist_id', 'status']);
        });

        Schema::create('mission_media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // photo, document, report_attachment.
            $table->string('type')->default('photo');

            // before, during, after.
            $table->string('stage')->nullable();

            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['mission_id', 'type']);
            $table->index(['mission_id', 'stage']);
        });

        Schema::create('mission_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('event');
            $table->string('title')->nullable();
            $table->text('description')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['mission_id', 'created_at']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_histories');
        Schema::dropIfExists('mission_media');
        Schema::dropIfExists('mission_checklist_items');
        Schema::dropIfExists('mission_checklists');
        Schema::dropIfExists('mission_positions');
        Schema::dropIfExists('mission_tracking_sessions');
        Schema::dropIfExists('mission_verification_codes');
        Schema::dropIfExists('mission_assignments');
        Schema::dropIfExists('missions');
    }
};
