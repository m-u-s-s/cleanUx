<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'managed_service_zone_id')) {
                    $table->unsignedBigInteger('managed_service_zone_id')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('employee_zone_assignments')) {
            Schema::table('employee_zone_assignments', function (Blueprint $table) {
                if (! Schema::hasColumn('employee_zone_assignments', 'assignment_type')) {
                    $table->string('assignment_type')->default('primary');
                }

                if (! Schema::hasColumn('employee_zone_assignments', 'coverage_priority')) {
                    $table->unsignedInteger('coverage_priority')->default(0);
                }

                if (! Schema::hasColumn('employee_zone_assignments', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }

                if (! Schema::hasColumn('employee_zone_assignments', 'starts_at')) {
                    $table->timestamp('starts_at')->nullable();
                }

                if (! Schema::hasColumn('employee_zone_assignments', 'ends_at')) {
                    $table->timestamp('ends_at')->nullable();
                }

                if (! Schema::hasColumn('employee_zone_assignments', 'notes')) {
                    $table->text('notes')->nullable();
                }
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                if (! Schema::hasColumn('bookings', 'duree')) {
                    $table->unsignedInteger('duree')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'duree_estimee')) {
                    $table->unsignedInteger('duree_estimee')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'devis_estime')) {
                    $table->decimal('devis_estime', 10, 2)->nullable();
                }

                if (! Schema::hasColumn('bookings', 'motif')) {
                    $table->text('motif')->nullable();
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

                if (! Schema::hasColumn('bookings', 'type_lieu')) {
                    $table->string('type_lieu')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'surface')) {
                    $table->string('surface')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'frequence')) {
                    $table->string('frequence')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'telephone_client')) {
                    $table->string('telephone_client')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'priorite')) {
                    $table->string('priorite')->default('normale');
                }

                if (! Schema::hasColumn('bookings', 'commentaire_client')) {
                    $table->text('commentaire_client')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'options_prestation')) {
                    $table->json('options_prestation')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'zones_specifiques')) {
                    $table->json('zones_specifiques')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'materiel_specifique')) {
                    $table->json('materiel_specifique')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'presence_animaux')) {
                    $table->boolean('presence_animaux')->default(false);
                }

                if (! Schema::hasColumn('bookings', 'acces_parking')) {
                    $table->boolean('acces_parking')->default(false);
                }

                if (! Schema::hasColumn('bookings', 'materiel_fournit')) {
                    $table->boolean('materiel_fournit')->default(false);
                }

                if (! Schema::hasColumn('bookings', 'is_recurrent')) {
                    $table->boolean('is_recurrent')->default(false);
                }

                if (! Schema::hasColumn('bookings', 'recurrence_rule')) {
                    $table->string('recurrence_rule')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'recurrence_frequency')) {
                    $table->string('recurrence_frequency')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'recurrence_interval')) {
                    $table->unsignedInteger('recurrence_interval')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'recurrence_until')) {
                    $table->date('recurrence_until')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'recurrence_count')) {
                    $table->unsignedInteger('recurrence_count')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'recurrence_days')) {
                    $table->json('recurrence_days')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'is_series_master')) {
                    $table->boolean('is_series_master')->default(false);
                }

                if (! Schema::hasColumn('bookings', 'series_position')) {
                    $table->unsignedInteger('series_position')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'series_status')) {
                    $table->string('series_status')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'is_favorite_slot')) {
                    $table->boolean('is_favorite_slot')->default(false);
                }

                if (! Schema::hasColumn('bookings', 'photos_reference')) {
                    $table->json('photos_reference')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'mission_started_at')) {
                    $table->timestamp('mission_started_at')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'mission_finished_at')) {
                    $table->timestamp('mission_finished_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        //
    }
};
