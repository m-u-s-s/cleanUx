<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->corpsInitial();
        $this->fusion20260508100002AddTimerToMissionAssignments();
        $this->fusion20260508120001AddEtaColumnsToMissions();
        $this->fusion20260514235147FixMissionVerificationCodesRuntimeColumns();
        $this->fusion20260528100001AddEmployeeCostToMissionsTable();
        $this->fusion20260528100002AddQualityScoreStatusToMissionsTable();
        $this->fusion20260528100024AddClientFinalStatusToMissionsTable();
        $this->fusion20260528100025AddArrivedAtToMissionAssignmentsTable();
        $this->fusion20260528100026AddMissingColumnsToMissionChecklistItemsTable();
        $this->fusion20260528100031AddMissingColumnsToMissionMediaTable();
        $this->fusion20260528100036AddMissingColumnsToMissionTrackingSessionsTabl();
        $this->fusion20260731000002AddEndGeoProofToMissions();
        $this->fusion20260815090000GuiderLaChecklistPasAPas();
        $this->fusion20260901090000RetirerLesColonnesDormantesDesAffectations();
        $this->fusion20260903090000PorterLaTodoDuClient();
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

    /** Le corps d origine, extrait pour que son `return` ne quitte que lui. */
    private function corpsInitial(): void
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

    /** Fusionne depuis 2026_05_08_100002_add_timer_to_mission_assignments */
    private function fusion20260508100002AddTimerToMissionAssignments(): void
    {
        if (! Schema::hasTable('mission_assignments')) {
            return;
        }

        Schema::table('mission_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_assignments', 'notification_sent_at')) {
                $table->timestamp('notification_sent_at')->nullable();
            }
            if (! Schema::hasColumn('mission_assignments', 'expires_at')) {
                $table->timestamp('expires_at')->nullable();
            }
            if (! Schema::hasColumn('mission_assignments', 'response_seconds')) {
                $table->unsignedSmallInteger('response_seconds')->nullable();
            }
            if (! Schema::hasColumn('mission_assignments', 'decline_reason')) {
                $table->string('decline_reason', 255)->nullable();
            }
            if (! Schema::hasColumn('mission_assignments', 'escalated_from_assignment_id')) {
                $table->foreignId('escalated_from_assignment_id')
                    ->nullable()

                    ->constrained('mission_assignments')
                    ->nullOnDelete();
            }

        });

        // Index pour la recherche "assignments expirés à escalader"
        Schema::table('mission_assignments', function (Blueprint $table) {
            try {
                $table->index(['expires_at', 'assignment_status'], 'mission_assign_expiry_idx');
            } catch (Throwable $e) {
                // Index existe déjà
            }
        });
    }

    /** Fusionne depuis 2026_05_08_120001_add_eta_columns_to_missions */
    private function fusion20260508120001AddEtaColumnsToMissions(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'last_eta_meters')) {
                $table->unsignedInteger('last_eta_meters')->nullable();
            }
            if (! Schema::hasColumn('missions', 'last_eta_seconds')) {
                $table->unsignedInteger('last_eta_seconds')->nullable();
            }
            if (! Schema::hasColumn('missions', 'last_eta_source')) {
                $table->string('last_eta_source', 30)->nullable();
            }
            if (! Schema::hasColumn('missions', 'last_eta_calculated_at')) {
                $table->timestamp('last_eta_calculated_at')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_14_235147_fix_mission_verification_codes_runtime_columns */
    private function fusion20260514235147FixMissionVerificationCodesRuntimeColumns(): void
    {
        if (! Schema::hasTable('mission_verification_codes')) {
            return;
        }

        Schema::table('mission_verification_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_verification_codes', 'code_type')) {
                $table->string('code_type')->default('start');
            }

            if (! Schema::hasColumn('mission_verification_codes', 'code_hash')) {
                $table->string('code_hash')->nullable();
            }

            if (! Schema::hasColumn('mission_verification_codes', 'expires_at')) {
                $table->timestamp('expires_at')->nullable();
            }

            if (! Schema::hasColumn('mission_verification_codes', 'validated_by_user_id')) {
                $table->foreignId('validated_by_user_id')->nullable();
            }

            if (! Schema::hasColumn('mission_verification_codes', 'validated_at')) {
                $table->timestamp('validated_at')->nullable();
            }

            if (! Schema::hasColumn('mission_verification_codes', 'attempts')) {
                $table->unsignedInteger('attempts')->default(0);
            }

            if (! Schema::hasColumn('mission_verification_codes', 'is_consumed')) {
                $table->boolean('is_consumed')->default(false);
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100001_add_employee_cost_to_missions_table */
    private function fusion20260528100001AddEmployeeCostToMissionsTable(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'employee_cost')) {
                $table->decimal('employee_cost', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('missions', 'travel_duration_minutes')) {
                $table->unsignedInteger('travel_duration_minutes')->nullable();
            }
            if (! Schema::hasColumn('missions', 'quality_summary')) {
                $table->json('quality_summary')->nullable();
            }
            if (! Schema::hasColumn('missions', 'client_final_validated_at')) {
                $table->timestamp('client_final_validated_at')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100002_add_quality_score_status_to_missions_table */
    private function fusion20260528100002AddQualityScoreStatusToMissionsTable(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'quality_score')) {
                $table->unsignedSmallInteger('quality_score')->nullable();
            }
            if (! Schema::hasColumn('missions', 'quality_status')) {
                $table->string('quality_status')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100024_add_client_final_status_to_missions_table */
    private function fusion20260528100024AddClientFinalStatusToMissionsTable(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'client_final_status')) {
                $table->string('client_final_status')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100025_add_arrived_at_to_mission_assignments_table */
    private function fusion20260528100025AddArrivedAtToMissionAssignmentsTable(): void
    {
        if (! Schema::hasTable('mission_assignments')) {
            return;
        }

        Schema::table('mission_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_assignments', 'arrived_at')) {
                $table->timestamp('arrived_at')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100026_add_missing_columns_to_mission_checklist_items_table */
    private function fusion20260528100026AddMissingColumnsToMissionChecklistItemsTable(): void
    {
        if (! Schema::hasTable('mission_checklist_items')) {
            return;
        }

        Schema::table('mission_checklist_items', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_checklist_items', 'completed_by_user_id')) {
                $table->unsignedBigInteger('completed_by_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_checklist_items', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100031_add_missing_columns_to_mission_media_table */
    private function fusion20260528100031AddMissingColumnsToMissionMediaTable(): void
    {
        if (! Schema::hasTable('mission_media')) {
            return;
        }

        Schema::table('mission_media', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_media', 'uploaded_by_user_id')) {
                $table->unsignedBigInteger('uploaded_by_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_media', 'media_type')) {
                $table->string('media_type')->nullable();
            }
            if (! Schema::hasColumn('mission_media', 'caption')) {
                $table->string('caption')->nullable();
            }
            if (! Schema::hasColumn('mission_media', 'taken_at')) {
                $table->timestamp('taken_at')->nullable();
            }
            if (! Schema::hasColumn('mission_media', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('mission_media', 'lng')) {
                $table->decimal('lng', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('mission_media', 'meta')) {
                $table->json('meta')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100036_add_missing_columns_to_mission_tracking_sessions_table */
    private function fusion20260528100036AddMissingColumnsToMissionTrackingSessionsTabl(): void
    {
        if (! Schema::hasTable('mission_tracking_sessions')) {
            return;
        }

        Schema::table('mission_tracking_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_tracking_sessions', 'assignment_id')) {
                $table->unsignedBigInteger('assignment_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_tracking_sessions', 'is_client_visible')) {
                $table->boolean('is_client_visible')->default(false);
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
            if (! Schema::hasColumn('mission_tracking_sessions', 'point_count')) {
                $table->unsignedInteger('point_count')->nullable();
            }
            if (! Schema::hasColumn('mission_tracking_sessions', 'distance_meters')) {
                $table->integer('distance_meters')->nullable();
            }
            if (! Schema::hasColumn('mission_tracking_sessions', 'meta')) {
                $table->json('meta')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_07_31_000002_add_end_geo_proof_to_missions */
    private function fusion20260731000002AddEndGeoProofToMissions(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'end_accuracy_m')) {
                $table->decimal('end_accuracy_m', 8, 1)->nullable();
            }
            if (! Schema::hasColumn('missions', 'end_distance_m')) {
                $table->unsignedInteger('end_distance_m')->nullable();
            }
            if (! Schema::hasColumn('missions', 'end_geo_verdict')) {
                $table->string('end_geo_verdict', 32)->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_08_15_090000_guider_la_checklist_pas_a_pas */
    private function fusion20260815090000GuiderLaChecklistPasAPas(): void
    {
        if (! Schema::hasTable('mission_checklist_items')) {
            return;
        }

        Schema::table('mission_checklist_items', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_checklist_items', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->nullable();
            }

            if (! Schema::hasColumn('mission_checklist_items', 'requires_photo')) {
                $table->boolean('requires_photo')->default(false);
            }

            if (! Schema::hasColumn('mission_checklist_items', 'mission_media_id')) {
                // La photo prise pour CETTE étape. Sans ce lien, le rapport ne saurait pas laquelle
                // des vingt photos de la mission atteste laquelle des vingt étapes.
                $table->unsignedBigInteger('mission_media_id')->nullable();
            }

            if (! Schema::hasColumn('mission_checklist_items', 'guidance')) {
                // La consigne de l'étape : ce qu'on attend, en une phrase. C'est ce qui fait la
                // différence entre « Sols » et « Aspirer puis laver, produit neutre sur parquet ».
                $table->text('guidance')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_09_01_090000_retirer_les_colonnes_dormantes_des_affectations */
    private function fusion20260901090000RetirerLesColonnesDormantesDesAffectations(): void
    {
        if (! Schema::hasTable('mission_assignments')) {
            return;
        }

        /*
         * TROIS ETAPES, ET CHAQUE MOTEUR A EXIGE LA SIENNE.
         *
         * `mission_assignments_user_id_status_index` porte `(user_id, status)`, et les deux bases
         * s'y opposent pour des raisons OPPOSEES :
         *
         *   — SQLite refuse qu'on retire `status` en laissant l'index pendre : toute operation
         *     ulterieure sur la table echoue avec « error in index … after drop column ». La suite
         *     de tests entiere tombait, alors que MySQL, lui, reecrit l'index tout seul.
         *   — MySQL refuse qu'on retire l'index : il SOUTIENT la cle etrangere de `user_id`
         *     (erreur 1553, « needed in a foreign key constraint »). SQLite, lui, s'en moque.
         *
         * D'ou l'ordre ci-dessous, qui satisfait les deux sans brancher sur le pilote : on donne
         * d'abord a la cle etrangere un index a elle, puis on retire le composite, puis la colonne.
         *
         * Le piege habituel de ce depot est RETOURNE : d'ordinaire SQLite cache ce que MySQL
         * refuse. Ici chacun a cache la moitie du probleme, et il a fallu exercer les DEUX.
         */
        Schema::table('mission_assignments', function (Blueprint $table) {
            $table->index('user_id', 'mission_assignments_user_id_index');
        });

        Schema::table('mission_assignments', function (Blueprint $table) {
            $table->dropIndex('mission_assignments_user_id_status_index');
        });

        Schema::table('mission_assignments', function (Blueprint $table) {
            foreach (['role', 'status'] as $dormante) {
                if (Schema::hasColumn('mission_assignments', $dormante)) {
                    $table->dropColumn($dormante);
                }
            }
        });
    }

    /** Fusionne depuis 2026_09_03_090000_porter_la_todo_du_client */
    private function fusion20260903090000PorterLaTodoDuClient(): void
    {
        if (! Schema::hasTable('mission_checklist_items')) {
            return;
        }

        Schema::table('mission_checklist_items', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_checklist_items', 'source')) {
                $table->string('source', 16)->default('template');
            }

            if (! Schema::hasColumn('mission_checklist_items', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable();
            }

            if (! Schema::hasColumn('mission_checklist_items', 'locked_at')) {
                $table->timestamp('locked_at')->nullable();
            }
        });

        /*
         * « LES TÂCHES DU CLIENT SUR CETTE LISTE » — la seule requête nouvelle, et elle sera posée
         * à chaque ouverture de l'écran des deux côtés.
         *
         * Le nom est tenu court À DESSEIN : au-delà de 64 caractères, MySQL refuse la migration, et
         * SQLite l'accepte sans rien dire — la classe de défaut invisible à la suite de tests.
         */
        if (! $this->fusion20260903090000PorterLaTodoDuClientAideIndexExiste('mci_liste_source_index')) {
            Schema::table('mission_checklist_items', function (Blueprint $table) {
                $table->index(['mission_checklist_id', 'source'], 'mci_liste_source_index');
            });
        }
    }

    private function fusion20260903090000PorterLaTodoDuClientAideIndexExiste(string $nom): bool
    {
        return collect(Schema::getIndexes('mission_checklist_items'))
            ->contains(fn (array $index) => ($index['name'] ?? null) === $nom);
    }
};
