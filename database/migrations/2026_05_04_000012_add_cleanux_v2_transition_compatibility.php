<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Bookings legacy compatibility
        |--------------------------------------------------------------------------
        | Objectif :
        | - garder bookings comme nouvelle table officielle
        | - permettre à l'ancien modèle RendezVous de fonctionner temporairement
        */

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                if (! Schema::hasColumn('bookings', 'client_id')) {
                    $table->foreignId('client_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('bookings', 'employe_id')) {
                    $table->foreignId('employe_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('bookings', 'date')) {
                    $table->date('date')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'heure')) {
                    $table->time('heure')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'adresse')) {
                    $table->string('adresse')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'ville')) {
                    $table->string('ville')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'code_postal')) {
                    $table->string('code_postal')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'telephone_client')) {
                    $table->string('telephone_client')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'commentaire_client')) {
                    $table->text('commentaire_client')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'devis_estime')) {
                    $table->decimal('devis_estime', 10, 2)->nullable();
                }

                if (! Schema::hasColumn('bookings', 'duree_estimee')) {
                    $table->integer('duree_estimee')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'statut')) {
                    $table->string('statut')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'source')) {
                    $table->string('source')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'payment_intent_id')) {
                    $table->string('payment_intent_id')->nullable()->index();
                }

                if (! Schema::hasColumn('bookings', 'payment_status')) {
                    $table->string('payment_status')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'stripe_connect_transfer_id')) {
                    $table->string('stripe_connect_transfer_id')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'stripe_connect_payment_status')) {
                    $table->string('stripe_connect_payment_status')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'google_place_id')) {
                    $table->string('google_place_id')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'lat')) {
                    $table->decimal('lat', 10, 7)->nullable();
                }

                if (! Schema::hasColumn('bookings', 'lng')) {
                    $table->decimal('lng', 10, 7)->nullable();
                }

                if (! Schema::hasColumn('bookings', 'asap_requested_at')) {
                    $table->timestamp('asap_requested_at')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'asap_deadline_at')) {
                    $table->timestamp('asap_deadline_at')->nullable();
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Missions legacy compatibility
        |--------------------------------------------------------------------------
        | Objectif :
        | - garder booking_id comme nouvelle relation officielle
        | - ajouter temporairement les anciens champs utilisés par les services actuels
        */

        if (Schema::hasTable('missions')) {
            Schema::table('missions', function (Blueprint $table) {
                if (! Schema::hasColumn('missions', 'rendez_vous_id')) {
                    $table->foreignId('rendez_vous_id')
                        ->nullable()
                        ->constrained('bookings')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('missions', 'lead_employee_id')) {
                    $table->foreignId('lead_employee_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('missions', 'organization_account_id')) {
                    $table->foreignId('organization_account_id')
                        ->nullable()
                        ->constrained('organization_accounts')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('missions', 'organization_site_id')) {
                    $table->foreignId('organization_site_id')
                        ->nullable()
                        ->constrained('organization_sites')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('missions', 'service_catalog_id')) {
                    $table->foreignId('service_catalog_id')
                        ->nullable()
                        ->constrained('service_catalogs')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('missions', 'service_zone_id')) {
                    $table->foreignId('service_zone_id')
                        ->nullable()
                        ->constrained('service_zones')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('missions', 'planned_end_at')) {
                    $table->timestamp('planned_end_at')->nullable();
                }

                if (! Schema::hasColumn('missions', 'requires_start_code')) {
                    $table->boolean('requires_start_code')->default(true);
                }

                if (! Schema::hasColumn('missions', 'requires_end_code')) {
                    $table->boolean('requires_end_code')->default(true);
                }

                if (! Schema::hasColumn('missions', 'start_code_hash')) {
                    $table->string('start_code_hash')->nullable();
                }

                if (! Schema::hasColumn('missions', 'end_code_hash')) {
                    $table->string('end_code_hash')->nullable();
                }

                if (! Schema::hasColumn('missions', 'start_code_validated_at')) {
                    $table->timestamp('start_code_validated_at')->nullable();
                }

                if (! Schema::hasColumn('missions', 'end_code_validated_at')) {
                    $table->timestamp('end_code_validated_at')->nullable();
                }

                if (! Schema::hasColumn('missions', 'destination_lat')) {
                    $table->decimal('destination_lat', 10, 7)->nullable();
                }

                if (! Schema::hasColumn('missions', 'destination_lng')) {
                    $table->decimal('destination_lng', 10, 7)->nullable();
                }

                if (! Schema::hasColumn('missions', 'travel_duration_minutes')) {
                    $table->integer('travel_duration_minutes')->nullable();
                }

                if (! Schema::hasColumn('missions', 'employee_cost')) {
                    $table->decimal('employee_cost', 10, 2)->nullable();
                }

                if (! Schema::hasColumn('missions', 'client_validation_required')) {
                    $table->boolean('client_validation_required')->default(true);
                }

                if (! Schema::hasColumn('missions', 'client_validated_at')) {
                    $table->timestamp('client_validated_at')->nullable();
                }

                if (! Schema::hasColumn('missions', 'notes')) {
                    $table->text('notes')->nullable();
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Mission assignments compatibility
        |--------------------------------------------------------------------------
        */

        if (Schema::hasTable('mission_assignments')) {
            Schema::table('mission_assignments', function (Blueprint $table) {
                if (! Schema::hasColumn('mission_assignments', 'role_on_mission')) {
                    $table->string('role_on_mission')->nullable();
                }

                if (! Schema::hasColumn('mission_assignments', 'assignment_status')) {
                    $table->string('assignment_status')->nullable();
                }

                if (! Schema::hasColumn('mission_assignments', 'assigned_at')) {
                    $table->timestamp('assigned_at')->nullable();
                }

                if (! Schema::hasColumn('mission_assignments', 'arrived_at')) {
                    $table->timestamp('arrived_at')->nullable();
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Mission tracking compatibility
        |--------------------------------------------------------------------------
        | Le code actuel utilise MissionTrackingSession + MissionTrackingPoint.
        | Donc on ajoute les colonnes attendues et la table mission_tracking_points.
        */

        if (Schema::hasTable('mission_tracking_sessions')) {
            Schema::table('mission_tracking_sessions', function (Blueprint $table) {
                if (! Schema::hasColumn('mission_tracking_sessions', 'assignment_id')) {
                    $table->foreignId('assignment_id')
                        ->nullable()
                        ->constrained('mission_assignments')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('mission_tracking_sessions', 'employee_user_id')) {
                    $table->foreignId('employee_user_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('mission_tracking_sessions', 'tracking_mode')) {
                    $table->string('tracking_mode')->default('live');
                }

                if (! Schema::hasColumn('mission_tracking_sessions', 'is_client_visible')) {
                    $table->boolean('is_client_visible')->default(true);
                }

                if (! Schema::hasColumn('mission_tracking_sessions', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }

                if (! Schema::hasColumn('mission_tracking_sessions', 'ended_at')) {
                    $table->timestamp('ended_at')->nullable();
                }

                if (! Schema::hasColumn('mission_tracking_sessions', 'start_lat')) {
                    $table->decimal('start_lat', 10, 7)->nullable();
                }

                if (! Schema::hasColumn('mission_tracking_sessions', 'start_lng')) {
                    $table->decimal('start_lng', 10, 7)->nullable();
                }

                if (! Schema::hasColumn('mission_tracking_sessions', 'last_lat')) {
                    $table->decimal('last_lat', 10, 7)->nullable();
                }

                if (! Schema::hasColumn('mission_tracking_sessions', 'last_lng')) {
                    $table->decimal('last_lng', 10, 7)->nullable();
                }

                if (! Schema::hasColumn('mission_tracking_sessions', 'point_count')) {
                    $table->unsignedInteger('point_count')->default(0);
                }

                if (! Schema::hasColumn('mission_tracking_sessions', 'distance_meters')) {
                    $table->unsignedInteger('distance_meters')->default(0);
                }

                if (! Schema::hasColumn('mission_tracking_sessions', 'meta')) {
                    $table->json('meta')->nullable();
                }
            });
        }

        if (! Schema::hasTable('mission_tracking_points')) {
            Schema::create('mission_tracking_points', function (Blueprint $table) {
                $table->id();

                $table->foreignId('tracking_session_id')
                    ->constrained('mission_tracking_sessions')
                    ->cascadeOnDelete();

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

        /*
        |--------------------------------------------------------------------------
        | 5. Feedback compatibility
        |--------------------------------------------------------------------------
        */

        if (Schema::hasTable('feedbacks')) {
            Schema::table('feedbacks', function (Blueprint $table) {
                if (! Schema::hasColumn('feedbacks', 'rendez_vous_id')) {
                    $table->foreignId('rendez_vous_id')
                        ->nullable()
                        ->constrained('bookings')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('feedbacks', 'client_id')) {
                    $table->foreignId('client_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('feedbacks', 'feedback')) {
                    $table->text('feedback')->nullable();
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Conversations compatibility
        |--------------------------------------------------------------------------
        | Nouveau système = channels/messages.
        | Ancien système = conversations/conversation_messages.
        | On garde temporairement les deux noms pour ne pas casser le code existant.
        */

        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();

                $table->foreignId('booking_id')
                    ->nullable()
                    ->constrained('bookings')
                    ->nullOnDelete();

                $table->foreignId('rendez_vous_id')
                    ->nullable()
                    ->constrained('bookings')
                    ->nullOnDelete();

                $table->foreignId('mission_id')
                    ->nullable()
                    ->constrained('missions')
                    ->nullOnDelete();

                $table->foreignId('client_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('employe_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('organization_account_id')
                    ->nullable()
                    ->constrained('organization_accounts')
                    ->nullOnDelete();

                $table->string('type')->default('booking');
                $table->string('status')->default('open');

                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index(['booking_id', 'status']);
                $table->index(['rendez_vous_id', 'status']);
                $table->index(['mission_id', 'status']);
            });
        }

        if (! Schema::hasTable('conversation_messages')) {
            Schema::create('conversation_messages', function (Blueprint $table) {
                $table->id();

                $table->foreignId('conversation_id')
                    ->constrained('conversations')
                    ->cascadeOnDelete();

                $table->foreignId('sender_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->text('body')->nullable();

                $table->string('type')->default('text');

                $table->json('attachments')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamp('read_at')->nullable();

                $table->timestamps();

                $table->index(['conversation_id', 'created_at']);
                $table->index('sender_id');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Paramètres legacy compatibility
        |--------------------------------------------------------------------------
        */

        if (! Schema::hasTable('parametres')) {
            Schema::create('parametres', function (Blueprint $table) {
                $table->id();

                $table->string('cle')->unique();
                $table->text('valeur')->nullable();
                $table->string('type')->default('string');

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('parametres')) {
            Schema::dropIfExists('parametres');
        }

        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');

        if (Schema::hasTable('feedbacks')) {
            Schema::table('feedbacks', function (Blueprint $table) {
                $columns = [
                    'feedback',
                    'client_id',
                    'rendez_vous_id',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('feedbacks', $column)) {
                        if (str_ends_with($column, '_id')) {
                            try {
                                $table->dropConstrainedForeignId($column);
                            } catch (Throwable) {
                                $table->dropColumn($column);
                            }
                        } else {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }

        Schema::dropIfExists('mission_tracking_points');

        if (Schema::hasTable('mission_tracking_sessions')) {
            Schema::table('mission_tracking_sessions', function (Blueprint $table) {
                $columns = [
                    'meta',
                    'distance_meters',
                    'point_count',
                    'last_lng',
                    'last_lat',
                    'start_lng',
                    'start_lat',
                    'ended_at',
                    'is_active',
                    'is_client_visible',
                    'tracking_mode',
                    'employee_user_id',
                    'assignment_id',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('mission_tracking_sessions', $column)) {
                        if (str_ends_with($column, '_id')) {
                            try {
                                $table->dropConstrainedForeignId($column);
                            } catch (Throwable) {
                                $table->dropColumn($column);
                            }
                        } else {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }

        if (Schema::hasTable('mission_assignments')) {
            Schema::table('mission_assignments', function (Blueprint $table) {
                foreach (['arrived_at', 'assigned_at', 'assignment_status', 'role_on_mission'] as $column) {
                    if (Schema::hasColumn('mission_assignments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('missions')) {
            Schema::table('missions', function (Blueprint $table) {
                $columns = [
                    'notes',
                    'client_validated_at',
                    'client_validation_required',
                    'employee_cost',
                    'travel_duration_minutes',
                    'destination_lng',
                    'destination_lat',
                    'end_code_validated_at',
                    'start_code_validated_at',
                    'end_code_hash',
                    'start_code_hash',
                    'requires_end_code',
                    'requires_start_code',
                    'planned_end_at',
                    'service_zone_id',
                    'service_catalog_id',
                    'organization_site_id',
                    'organization_account_id',
                    'lead_employee_id',
                    'rendez_vous_id',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('missions', $column)) {
                        if (str_ends_with($column, '_id')) {
                            try {
                                $table->dropConstrainedForeignId($column);
                            } catch (Throwable) {
                                $table->dropColumn($column);
                            }
                        } else {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                $columns = [
                    'asap_deadline_at',
                    'asap_requested_at',
                    'lng',
                    'lat',
                    'google_place_id',
                    'stripe_connect_payment_status',
                    'stripe_connect_transfer_id',
                    'payment_status',
                    'payment_intent_id',
                    'source',
                    'statut',
                    'duree_estimee',
                    'devis_estime',
                    'commentaire_client',
                    'telephone_client',
                    'code_postal',
                    'ville',
                    'adresse',
                    'heure',
                    'date',
                    'employe_id',
                    'client_id',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('bookings', $column)) {
                        if (str_ends_with($column, '_id')) {
                            try {
                                $table->dropConstrainedForeignId($column);
                            } catch (Throwable) {
                                $table->dropColumn($column);
                            }
                        } else {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }
    }
};