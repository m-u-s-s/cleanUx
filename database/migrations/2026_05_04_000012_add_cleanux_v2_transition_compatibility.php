<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $this->addBookingCompatibilityColumns($table);
        });

        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'rendez_vous_id')) {
                $table->foreignId('rendez_vous_id')->nullable()->after('booking_id')->constrained('bookings')->nullOnDelete();
            }
            if (! Schema::hasColumn('missions', 'organization_account_id')) {
                $table->foreignId('organization_account_id')->nullable()->constrained('organization_accounts')->nullOnDelete();
            }
            if (! Schema::hasColumn('missions', 'organization_site_id')) {
                $table->foreignId('organization_site_id')->nullable()->constrained('organization_sites')->nullOnDelete();
            }
            if (! Schema::hasColumn('missions', 'service_catalog_id')) {
                $table->foreignId('service_catalog_id')->nullable()->constrained('service_catalogs')->nullOnDelete();
            }
            if (! Schema::hasColumn('missions', 'service_zone_id')) {
                $table->foreignId('service_zone_id')->nullable()->constrained('service_zones')->nullOnDelete();
            }
            if (! Schema::hasColumn('missions', 'lead_employee_id')) {
                $table->foreignId('lead_employee_id')->nullable()->after('lead_provider_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('missions', 'mission_type')) $table->string('mission_type')->nullable();
            if (! Schema::hasColumn('missions', 'planned_end_at')) $table->timestamp('planned_end_at')->nullable();
            if (! Schema::hasColumn('missions', 'requires_start_code')) $table->boolean('requires_start_code')->default(true);
            if (! Schema::hasColumn('missions', 'requires_end_code')) $table->boolean('requires_end_code')->default(true);
            if (! Schema::hasColumn('missions', 'client_presence_confirmed')) $table->boolean('client_presence_confirmed')->default(false);
            if (! Schema::hasColumn('missions', 'started_by_user_id')) $table->foreignId('started_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('missions', 'closed_by_user_id')) $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('missions', 'notes')) $table->text('notes')->nullable();
            if (! Schema::hasColumn('missions', 'destination_lat')) $table->decimal('destination_lat', 10, 7)->nullable();
            if (! Schema::hasColumn('missions', 'destination_lng')) $table->decimal('destination_lng', 10, 7)->nullable();
            if (! Schema::hasColumn('missions', 'quality_score')) $table->unsignedTinyInteger('quality_score')->nullable();
            if (! Schema::hasColumn('missions', 'quality_status')) $table->string('quality_status')->nullable();
            if (! Schema::hasColumn('missions', 'client_final_status')) $table->string('client_final_status')->nullable();
            if (! Schema::hasColumn('missions', 'client_final_validated_at')) $table->timestamp('client_final_validated_at')->nullable();
            if (! Schema::hasColumn('missions', 'quality_summary')) $table->json('quality_summary')->nullable();
            if (! Schema::hasColumn('missions', 'employee_cost')) $table->decimal('employee_cost', 10, 2)->nullable();
            if (! Schema::hasColumn('missions', 'travel_duration_minutes')) $table->unsignedInteger('travel_duration_minutes')->nullable();
            if (! Schema::hasColumn('missions', 'mission_task_segment_id')) $table->unsignedBigInteger('mission_task_segment_id')->nullable();
        });

        Schema::table('mission_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_assignments', 'role_on_mission')) $table->string('role_on_mission')->nullable();
            if (! Schema::hasColumn('mission_assignments', 'assignment_status')) $table->string('assignment_status')->nullable();
            if (! Schema::hasColumn('mission_assignments', 'assigned_at')) $table->timestamp('assigned_at')->nullable();
            if (! Schema::hasColumn('mission_assignments', 'arrived_at')) $table->timestamp('arrived_at')->nullable();
        });

        Schema::table('mission_tracking_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_tracking_sessions', 'assignment_id')) $table->foreignId('assignment_id')->nullable()->constrained('mission_assignments')->nullOnDelete();
            if (! Schema::hasColumn('mission_tracking_sessions', 'employee_user_id')) $table->foreignId('employee_user_id')->nullable()->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('mission_tracking_sessions', 'tracking_mode')) $table->string('tracking_mode')->default('to_client');
            if (! Schema::hasColumn('mission_tracking_sessions', 'is_client_visible')) $table->boolean('is_client_visible')->default(true);
            if (! Schema::hasColumn('mission_tracking_sessions', 'is_active')) $table->boolean('is_active')->default(true);
            if (! Schema::hasColumn('mission_tracking_sessions', 'ended_at')) $table->timestamp('ended_at')->nullable();
            if (! Schema::hasColumn('mission_tracking_sessions', 'start_lat')) $table->decimal('start_lat', 10, 7)->nullable();
            if (! Schema::hasColumn('mission_tracking_sessions', 'start_lng')) $table->decimal('start_lng', 10, 7)->nullable();
            if (! Schema::hasColumn('mission_tracking_sessions', 'last_lat')) $table->decimal('last_lat', 10, 7)->nullable();
            if (! Schema::hasColumn('mission_tracking_sessions', 'last_lng')) $table->decimal('last_lng', 10, 7)->nullable();
            if (! Schema::hasColumn('mission_tracking_sessions', 'point_count')) $table->unsignedInteger('point_count')->default(0);
            if (! Schema::hasColumn('mission_tracking_sessions', 'distance_meters')) $table->unsignedInteger('distance_meters')->default(0);
            if (! Schema::hasColumn('mission_tracking_sessions', 'meta')) $table->json('meta')->nullable();
        });

        if (! Schema::hasTable('mission_tracking_points')) {
            Schema::create('mission_tracking_points', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tracking_session_id')->constrained('mission_tracking_sessions')->cascadeOnDelete();
                $table->timestamp('recorded_at')->nullable();
                $table->decimal('lat', 10, 7);
                $table->decimal('lng', 10, 7);
                $table->decimal('accuracy_meters', 10, 2)->nullable();
                $table->decimal('speed_kmh', 10, 2)->nullable();
                $table->decimal('heading', 10, 2)->nullable();
                $table->unsignedTinyInteger('battery_level')->nullable();
                $table->string('source')->nullable();
                $table->string('app_state')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['tracking_session_id', 'recorded_at']);
            });
        }

        Schema::table('feedbacks', function (Blueprint $table) {
            if (! Schema::hasColumn('feedbacks', 'rendez_vous_id')) $table->foreignId('rendez_vous_id')->nullable()->constrained('bookings')->nullOnDelete();
            if (! Schema::hasColumn('feedbacks', 'client_id')) $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('feedbacks', 'feedback')) $table->text('feedback')->nullable();
        });

        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rendez_vous_id')->nullable()->constrained('bookings')->nullOnDelete();
                $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
                $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
                $table->foreignId('organization_account_id')->nullable()->constrained('organization_accounts')->nullOnDelete();
                $table->string('subject')->nullable();
                $table->string('status')->default('open');
                $table->timestamps();
                $table->index(['booking_id', 'status']);
                $table->index(['mission_id', 'status']);
            });
        }

        if (! Schema::hasTable('conversation_messages')) {
            Schema::create('conversation_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('body')->nullable();
                $table->json('attachments')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['conversation_id', 'created_at']);
            });
        }
    }

    private function addBookingCompatibilityColumns(Blueprint $table): void
    {
        $columns = [
            'client_id' => fn() => $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete(),
            'employe_id' => fn() => $table->foreignId('employe_id')->nullable()->constrained('users')->nullOnDelete(),
            'organization_account_id' => fn() => $table->foreignId('organization_account_id')->nullable()->constrained('organization_accounts')->nullOnDelete(),
            'date' => fn() => $table->date('date')->nullable(),
            'heure' => fn() => $table->time('heure')->nullable(),
            'duree' => fn() => $table->unsignedInteger('duree')->nullable(),
            'duree_estimee' => fn() => $table->unsignedInteger('duree_estimee')->nullable(),
            'devis_estime' => fn() => $table->decimal('devis_estime', 10, 2)->nullable(),
            'motif' => fn() => $table->string('motif')->nullable(),
            'adresse' => fn() => $table->string('adresse')->nullable(),
            'ville' => fn() => $table->string('ville')->nullable(),
            'code_postal' => fn() => $table->string('code_postal')->nullable(),
            'type_lieu' => fn() => $table->string('type_lieu')->nullable(),
            'surface' => fn() => $table->integer('surface')->nullable(),
            'frequence' => fn() => $table->string('frequence')->nullable(),
            'priorite' => fn() => $table->string('priorite')->nullable(),
            'commentaire_client' => fn() => $table->text('commentaire_client')->nullable(),
            'telephone_client' => fn() => $table->string('telephone_client')->nullable(),
            'presence_animaux' => fn() => $table->boolean('presence_animaux')->default(false),
            'acces_parking' => fn() => $table->boolean('acces_parking')->default(false),
            'materiel_fournit' => fn() => $table->boolean('materiel_fournit')->default(false),
            'options_prestation' => fn() => $table->json('options_prestation')->nullable(),
            'zones_specifiques' => fn() => $table->json('zones_specifiques')->nullable(),
            'materiel_specifique' => fn() => $table->json('materiel_specifique')->nullable(),
            'booking_channel' => fn() => $table->string('booking_channel')->default('web'),
            'is_recurrent' => fn() => $table->boolean('is_recurrent')->default(false),
            'recurrence_rule' => fn() => $table->string('recurrence_rule')->nullable(),
            'recurring_series_id' => fn() => $table->unsignedBigInteger('recurring_series_id')->nullable(),
            'recurrence_frequency' => fn() => $table->string('recurrence_frequency')->nullable(),
            'recurrence_interval' => fn() => $table->unsignedInteger('recurrence_interval')->nullable(),
            'recurrence_until' => fn() => $table->date('recurrence_until')->nullable(),
            'recurrence_count' => fn() => $table->unsignedInteger('recurrence_count')->nullable(),
            'recurrence_days' => fn() => $table->json('recurrence_days')->nullable(),
            'is_series_master' => fn() => $table->boolean('is_series_master')->default(false),
            'series_status' => fn() => $table->string('series_status')->nullable(),
            'is_favorite_slot' => fn() => $table->boolean('is_favorite_slot')->default(false),
            'terrain_checklist' => fn() => $table->json('terrain_checklist')->nullable(),
            'remarque_terrain' => fn() => $table->text('remarque_terrain')->nullable(),
            'incident_terrain' => fn() => $table->text('incident_terrain')->nullable(),
            'photos_avant' => fn() => $table->json('photos_avant')->nullable(),
            'photos_apres' => fn() => $table->json('photos_apres')->nullable(),
            'commentaire_fin_mission' => fn() => $table->text('commentaire_fin_mission')->nullable(),
            'duree_reelle' => fn() => $table->unsignedInteger('duree_reelle')->nullable(),
            'mission_started_at' => fn() => $table->timestamp('mission_started_at')->nullable(),
            'mission_arrived_at' => fn() => $table->timestamp('mission_arrived_at')->nullable(),
            'mission_finished_at' => fn() => $table->timestamp('mission_finished_at')->nullable(),
            'client_presence_confirmed_at' => fn() => $table->timestamp('client_presence_confirmed_at')->nullable(),
            'client_signature_path' => fn() => $table->string('client_signature_path')->nullable(),
            'rappel_24h_envoye_at' => fn() => $table->timestamp('rappel_24h_envoye_at')->nullable(),
            'rappel_2h_envoye_at' => fn() => $table->timestamp('rappel_2h_envoye_at')->nullable(),
            'feedback_demande_envoye_at' => fn() => $table->timestamp('feedback_demande_envoye_at')->nullable(),
            'alerte_urgence_envoyee_at' => fn() => $table->timestamp('alerte_urgence_envoyee_at')->nullable(),
            'asap_requested_at' => fn() => $table->timestamp('asap_requested_at')->nullable(),
            'asap_deadline_at' => fn() => $table->timestamp('asap_deadline_at')->nullable(),
            'matched_at' => fn() => $table->timestamp('matched_at')->nullable(),
            'payment_status' => fn() => $table->string('payment_status')->nullable(),
            'stripe_payment_intent_id' => fn() => $table->string('stripe_payment_intent_id')->nullable()->index(),
            'payment_authorized_at' => fn() => $table->timestamp('payment_authorized_at')->nullable(),
            'payment_captured_at' => fn() => $table->timestamp('payment_captured_at')->nullable(),
            'payment_cancelled_at' => fn() => $table->timestamp('payment_cancelled_at')->nullable(),
            'payment_failed_at' => fn() => $table->timestamp('payment_failed_at')->nullable(),
            'stripe_connect_account_id' => fn() => $table->string('stripe_connect_account_id')->nullable(),
            'payment_amount_cents' => fn() => $table->unsignedInteger('payment_amount_cents')->nullable(),
            'platform_fee_cents' => fn() => $table->unsignedInteger('platform_fee_cents')->nullable(),
            'provider_amount_cents' => fn() => $table->unsignedInteger('provider_amount_cents')->nullable(),
            'google_place_id' => fn() => $table->string('google_place_id')->nullable(),
            'destination_lat' => fn() => $table->decimal('destination_lat', 10, 7)->nullable(),
            'destination_lng' => fn() => $table->decimal('destination_lng', 10, 7)->nullable(),
            'address_components' => fn() => $table->json('address_components')->nullable(),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('bookings', $column)) {
                $definition();
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('mission_tracking_points');
    }
};
