<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration de consolidation — extras non couverts par 2026_05_04_*
 *
 * Regroupe en une seule migration propre les tables supplémentaires
 * que le projet utilise et qui ne sont pas dans le nouveau schéma principal.
 *
 * ORDRE : doit tourner APRÈS tous les 2026_05_04_* (date 2026_05_05)
 *
 * Tables créées :
 *   parametres               — Configuration système clé/valeur
 *   platform_modules         — Feature flags / modules activables
 *   location_geocodes        — Cache de géocodage
 *   email_logs               — Journal des emails envoyés
 *   google_calendar_*        — Connexions Google Calendar
 *   subscription_plans       — Plans d'abonnement disponibles
 *   client_subscriptions     — Abonnements souscrits par les clients
 *   pricing_logs             — Journal de tarification dynamique
 *   incident_reports         — Incidents terrain
 *   complaint_cases          — Litiges / réclamations clients
 *   quality_audits           — Audits qualité
 *   mission_batches          — Lots de missions
 *   mission_batch_days       — Jours dans un batch
 *   mission_task_segments    — Segments de tâches
 *   mission_member_statuses  — Statut terrain par membre
 *   customer_claims          — Réclamations clients
 */
return new class extends Migration
{
    public function up(): void
    {
        // ────────────────────────────────────────────────────
        // 1. Paramètres système (clé / valeur, sans FK)
        // ────────────────────────────────────────────────────
        Schema::create('parametres', function (Blueprint $table) {
            $table->id();
            $table->string('cle')->unique();
            $table->text('valeur')->nullable();
            $table->string('type')->default('string'); // string, bool, json, int
            $table->string('groupe')->nullable()->index();
            $table->timestamps();
        });

        // ────────────────────────────────────────────────────
        // 2. Feature flags / modules plateforme (sans FK)
        // ────────────────────────────────────────────────────
        Schema::create('platform_modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->default('core');
            $table->string('rollout_strategy')->default('global');
            // global | role | plan | zone | organization
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->json('settings')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();

            $table->index(['category', 'is_enabled']);
        });

        // ────────────────────────────────────────────────────
        // 3. Cache géocodage (sans FK)
        // ────────────────────────────────────────────────────
        Schema::create('location_geocodes', function (Blueprint $table) {
            $table->id();
            $table->string('lookup_hash')->unique();
            $table->string('address_line')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('provider')->default('nominatim');
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['postal_code', 'city', 'country_code']);
        });

        // ────────────────────────────────────────────────────
        // 4. Email logs (FK → users)
        // ────────────────────────────────────────────────────
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('template_key')->nullable()->index();
            $table->string('subject')->nullable();
            $table->string('status', 32)->default('sent')->index();
            $table->string('channel', 32)->default('mail')->index();
            $table->string('recipient_email')->nullable()->index();
            $table->nullableMorphs('notifiable');
            $table->foreignId('previewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->json('context')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamps();
        });

        // ────────────────────────────────────────────────────
        // 5. Google Calendar (FK → users)
        // ────────────────────────────────────────────────────
        Schema::create('google_calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('google_email')->nullable();
            $table->string('google_user_id')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('calendar_id')->default('primary');
            $table->text('scope')->nullable();
            $table->boolean('sync_enabled')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('google_calendar_event_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('google_calendar_connection_id')
                ->constrained('google_calendar_connections')
                ->cascadeOnDelete();
            $table->foreignId('booking_id')
                ->nullable()
                ->constrained('bookings')
                ->cascadeOnDelete();
            $table->string('google_event_id');
            $table->string('google_calendar_id')->default('primary');
            $table->string('etag')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['google_calendar_connection_id', 'google_event_id']);
        });

        // ────────────────────────────────────────────────────
        // 6. Plans & abonnements clients (FK → users)
        // ────────────────────────────────────────────────────
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('frequency_per_month')->default(4); // 4 = weekly
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('discount_rate', 5, 2)->default(0); // %
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable();
            $table->timestamps();
        });

        Schema::create('client_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('plan_id')
                ->constrained('subscription_plans')
                ->cascadeOnDelete();
            // active | paused | cancelled | past_due
            $table->string('status')->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });

        // ────────────────────────────────────────────────────
        // 7. Pricing logs (sans FK critique)
        // ────────────────────────────────────────────────────
        Schema::create('pricing_logs', function (Blueprint $table) {
            $table->id();
            $table->string('zone')->index();
            $table->tinyInteger('hour');
            $table->integer('demand_score');
            $table->integer('supply_score');
            $table->decimal('price', 10, 2);
            $table->boolean('accepted')->default(false);
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['zone', 'hour']);
        });

        // ────────────────────────────────────────────────────
        // 8. Qualité terrain (FK → users, missions, bookings)
        // ────────────────────────────────────────────────────
        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')
                ->nullable()
                ->constrained('missions')
                ->cascadeOnDelete();
            $table->foreignId('reported_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            // equipment | client | access | safety | other
            $table->string('type')->default('other');
            $table->text('description');
            // open | in_progress | resolved | closed
            $table->string('status')->default('open');
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['mission_id', 'status']);
        });

        Schema::create('complaint_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('client_organization_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->nullOnDelete();
            $table->foreignId('booking_id')
                ->nullable()
                ->constrained('bookings')
                ->nullOnDelete();
            $table->foreignId('mission_id')
                ->nullable()
                ->constrained('missions')
                ->nullOnDelete();
            $table->string('subject');
            $table->text('description');
            // open | in_progress | resolved | closed | refunded
            $table->string('status')->default('open');
            $table->text('internal_notes')->nullable();
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['client_user_id', 'status']);
        });

        Schema::create('quality_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')
                ->nullable()
                ->constrained('missions')
                ->nullOnDelete();
            $table->foreignId('auditor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->tinyInteger('score')->unsigned()->nullable(); // 0-100
            $table->text('observations')->nullable();
            $table->json('checklist_results')->nullable();
            $table->string('status')->default('draft'); // draft | submitted | validated
            $table->timestamp('audited_at')->nullable();
            $table->timestamps();
        });

        // ────────────────────────────────────────────────────
        // 9. Mission batches (FK → missions, organizations, sites)
        // ────────────────────────────────────────────────────
        Schema::create('mission_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_account_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('status')->default('draft');
            // draft | scheduled | in_progress | completed | cancelled
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_account_id', 'status']);
        });

        Schema::create('mission_batch_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_batch_id')
                ->constrained('mission_batches')
                ->cascadeOnDelete();
            $table->date('day');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['mission_batch_id', 'day']);
        });

        Schema::create('mission_task_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();
            $table->foreignId('mission_batch_day_id')
                ->nullable()
                ->constrained('mission_batch_days')
                ->nullOnDelete();
            $table->string('label');
            $table->integer('estimated_minutes')->default(60);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        // ────────────────────────────────────────────────────
        // 10. Statuts membre terrain
        // ────────────────────────────────────────────────────
        Schema::create('mission_member_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            // on_the_way | arrived | working | done | absent
            $table->string('status')->default('on_the_way');
            $table->timestamp('status_at')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['mission_id', 'user_id']);
            $table->index(['mission_id', 'status']);
        });

        // ────────────────────────────────────────────────────
        // 11. Réclamations clients
        // ────────────────────────────────────────────────────
        Schema::create('customer_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('booking_id')
                ->nullable()
                ->constrained('bookings')
                ->nullOnDelete();
            $table->foreignId('mission_id')
                ->nullable()
                ->constrained('missions')
                ->nullOnDelete();
            $table->string('category')->default('quality');
            // quality | billing | no_show | damage | other
            $table->string('status')->default('open');
            $table->text('description');
            $table->text('admin_response')->nullable();
            $table->decimal('compensation_amount', 10, 2)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['client_user_id', 'status']);
        });
    }

    // ─────────────────────────────────────────────────────────
    // DOWN — dans l'ordre inverse des dépendances FK
    // ─────────────────────────────────────────────────────────
    public function down(): void
    {
        Schema::dropIfExists('customer_claims');
        Schema::dropIfExists('mission_member_statuses');
        Schema::dropIfExists('mission_task_segments');
        Schema::dropIfExists('mission_batch_days');
        Schema::dropIfExists('mission_batches');
        Schema::dropIfExists('quality_audits');
        Schema::dropIfExists('complaint_cases');
        Schema::dropIfExists('incident_reports');
        Schema::dropIfExists('pricing_logs');
        Schema::dropIfExists('client_subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('google_calendar_event_links');
        Schema::dropIfExists('google_calendar_connections');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('location_geocodes');
        Schema::dropIfExists('platform_modules');
        Schema::dropIfExists('parametres');
    }
};
