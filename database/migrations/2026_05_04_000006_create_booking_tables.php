<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_booking_series', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('customer_organization_id')
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

            $table->string('frequency')->default('weekly');
            $table->unsignedInteger('interval')->default(1);
            $table->json('days')->nullable();

            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('occurrence_count')->nullable();

            $table->string('status')->default('active');
            $table->string('timezone')->default('Europe/Brussels');

            $table->timestamp('next_occurrence_at')->nullable();
            $table->timestamp('last_generated_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_user_id', 'status']);
            $table->index(['customer_organization_id', 'status']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            $table->string('booking_reference')->nullable()->unique();

            $table->foreignId('customer_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Compatibilité legacy avec anciens tests / ancien code.
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('employe_id')->nullable();

            $table->foreignId('customer_organization_id')
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

            $table->foreignId('preferred_provider_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('assigned_provider_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('recurring_booking_series_id')
                ->nullable()
                ->constrained('recurring_booking_series')
                ->nullOnDelete();

            $table->foreignId('parent_booking_id')
                ->nullable()
                ->constrained('bookings')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status')->default('pending');
            $table->string('booking_mode')->default('scheduled');
            $table->string('priority')->default('normal');

            // Colonnes legacy FR.
            $table->string('priorite')->nullable();
            $table->string('type_lieu')->nullable();
            $table->string('frequence')->nullable();

            // Données modernes.
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->timestamp('scheduled_at')->nullable();

            $table->string('place_type')->nullable();
            $table->string('frequency')->nullable();

            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->default('BE');

            $table->unsignedInteger('surface_m2')->nullable();
            $table->unsignedInteger('floor_count')->nullable();
            $table->unsignedInteger('rooms_count')->nullable();

            $table->text('customer_comment')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            // Colonnes legacy FR.
            $table->date('date')->nullable();
            $table->time('heure')->nullable();
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('code_postal')->nullable();
            $table->unsignedInteger('surface')->nullable();

            $table->string('currency', 3)->default('EUR');

            $table->decimal('estimated_price', 10, 2)->nullable();
            $table->decimal('final_price', 10, 2)->nullable();
            $table->decimal('zone_surcharge', 10, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();

            $table->boolean('requires_quote')->default(false);
            $table->boolean('is_urgent')->default(false);

            $table->unsignedInteger('occurrence_index')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->json('zone_snapshot')->nullable();
            $table->json('pricing_snapshot')->nullable();
            $table->json('service_snapshot')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['customer_user_id', 'status']);
            $table->index(['customer_organization_id', 'status']);
            $table->index(['service_catalog_id', 'status']);
            $table->index(['service_zone_id', 'status']);
            $table->index(['scheduled_date', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['employe_id', 'status']);
        });

        Schema::create('booking_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'to_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_histories');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('recurring_booking_series');
    }
};
