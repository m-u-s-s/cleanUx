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

            $table->foreignId('rendez_vous_id')
                ->constrained('rendez_vous')
                ->cascadeOnDelete();

            $table->foreignId('organization_account_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->nullOnDelete();

            $table->foreignId('organization_site_id')
                ->nullable()
                ->constrained('organization_sites')
                ->nullOnDelete();

            $table->foreignId('service_catalog_id')
                ->nullable()
                ->constrained('service_catalogs')
                ->nullOnDelete();

            $table->foreignId('service_zone_id')
                ->nullable()
                ->constrained('service_zones')
                ->nullOnDelete();

            $table->foreignId('lead_employee_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status')->default('planned');
            $table->string('mission_type')->default('standard');

            $table->dateTime('planned_start_at')->nullable();
            $table->dateTime('planned_end_at')->nullable();
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();

            $table->boolean('requires_start_code')->default(true);
            $table->boolean('requires_end_code')->default(true);
            $table->boolean('client_presence_confirmed')->default(false);

            $table->foreignId('started_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('closed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->decimal('start_lat', 10, 7)->nullable();
            $table->decimal('start_lng', 10, 7)->nullable();
            $table->decimal('end_lat', 10, 7)->nullable();
            $table->decimal('end_lng', 10, 7)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('mission_type');
            $table->index('planned_start_at');
            $table->index('planned_end_at');
            $table->index(['organization_account_id', 'status']);
            $table->index(['lead_employee_id', 'status']);
            $table->unique('rendez_vous_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};