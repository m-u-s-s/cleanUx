<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->corpsInitial();
        $this->fusion20260511230500FixBookingContactColumns();
        $this->fusion20260518100004AddStripeColumnsToBookingsTable();
        $this->fusion20260525000001AddPayoutColumnsToBookings();
        $this->fusion20260527000006AddContactFieldsToBookings();
        $this->fusion20260528100014AddMissingColumnsToBookingsTable();
        $this->fusion20260528100041AddTemplatePayloadToRecurringBookingSeriesTabl();
        $this->fusion20260602000001AddProviderTypePreferenceToBookings();
        $this->fusion20260608000002AddDateIndexesToBookings();
        $this->fusion20260801000700AddPaymentPlanToBookings();
        $this->fusion20260814090000ModeClientAbsentEtPingDeMiMission();
        $this->fusion20260907090000PorterLaConsigneDeDerniereMinute();
        $this->fusion20260908090000PorterLeMinuteurDeRetard();
        $this->fusionFixPortalAndBookingLegacyColumnsBookings();
        $this->fusionFixBrioTestSchemaCompatibilityFinalBookings();
        $this->fusionFixApprovalBookingRuntimeSchemaRound4Bookings();
        $this->fusionAddProviderOrgTeamToBookingAndMissionBookings();
        $this->fusionAddProviderOrgAndContractLinksForSp4Bookings();
        $this->fusionAddMissingReferencedColumnsBookings();
        $this->fusionPorterLesHeuresAcheteesBookings();
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_histories');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('recurring_booking_series');
    }

    /** Le corps d origine, extrait pour que son `return` ne quitte que lui. */
    private function corpsInitial(): void
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

            $table->text('customer_comment')->nullable();
            $table->text('description')->nullable();

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
            $table->decimal('discount_amount', 10, 2)->nullable();

            $table->boolean('requires_quote')->default(false);

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->json('zone_snapshot')->nullable();
            $table->json('pricing_snapshot')->nullable();
            $table->json('metadata')->nullable();

            /*
             * Le point d ARRIVEE et la route, fusionnes depuis
             * 2026_08_28_090000_porter_le_point_d_arrivee_sur_la_commande. Les memes huit colonnes
             * y etaient posees sur `order_drafts` et `bookings` par une boucle : elles vivent
             * desormais dans chacun des deux `create`.
             */
            $table->string('dropoff_address')->nullable();
            // Meme precision que `destination_lat` : sept decimales situent a un centimetre.
            $table->decimal('dropoff_lat', 10, 7)->nullable();
            $table->decimal('dropoff_lng', 10, 7)->nullable();
            $table->string('dropoff_postal_code', 12)->nullable();
            $table->unsignedInteger('route_distance_m')->nullable();
            $table->unsignedInteger('route_duration_s')->nullable();
            // `google`, `mapbox`, `haversine`... : une ligne droite ne doit pas se faire passer pour
            // une mesure routiere quand on relit le devis six mois plus tard.
            $table->string('route_source', 24)->nullable();
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

    /** Fusionne depuis 2026_05_11_230500_fix_booking_contact_columns */
    private function fusion20260511230500FixBookingContactColumns(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'contact_phone')) {
                $table->string('contact_phone')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'estimated_price')) {
                $table->decimal('estimated_price', 10, 2)->nullable();
            }

            if (! Schema::hasColumn('bookings', 'estimated_duration_minutes')) {
                $table->unsignedInteger('estimated_duration_minutes')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_18_100004_add_stripe_columns_to_bookings_table */
    private function fusion20260518100004AddStripeColumnsToBookingsTable(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'stripe_payment_intent_id')) {
                $table->string('stripe_payment_intent_id', 128)->nullable();
            }
            if (! Schema::hasColumn('bookings', 'payment_status')) {
                $table->string('payment_status', 32)->nullable();
            }
            if (! Schema::hasColumn('bookings', 'payment_amount_cents')) {
                $table->unsignedBigInteger('payment_amount_cents')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'provider_amount_cents')) {
                $table->unsignedBigInteger('provider_amount_cents')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'platform_fee_cents')) {
                $table->unsignedBigInteger('platform_fee_cents')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'payment_refunded_at')) {
                $table->timestamp('payment_refunded_at')->nullable();
            }

            // Colonnes payment_*_at référencées par le model Booking mais souvent
            // absentes en MySQL — on les crée si manquantes (idempotent).
            foreach ([
                'payment_authorized_at',
                'payment_captured_at',
                'payment_cancelled_at',
                'payment_failed_at',
            ] as $col) {
                if (! Schema::hasColumn('bookings', $col)) {
                    $table->timestamp($col)->nullable();
                }
            }
        });

        try {
            Schema::table('bookings', function (Blueprint $table) {
                $table->index('stripe_payment_intent_id', 'bookings_stripe_payment_intent_id_index');
            });
        } catch (Throwable $e) {
            // index already exists — ignore
        }
    }

    /** Fusionne depuis 2026_05_25_000001_add_payout_columns_to_bookings */
    private function fusion20260525000001AddPayoutColumnsToBookings(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'payout_status')) {
                $table->string('payout_status', 32)->nullable();
            }
            // provider_payout_cents — distinct from provider_amount_cents (the gross pre-fee amount)
            if (! Schema::hasColumn('bookings', 'provider_payout_cents')) {
                $table->unsignedBigInteger('provider_payout_cents')->nullable();
            }
            // stripe_transfer_id — set once the Stripe Connect transfer is created
            if (! Schema::hasColumn('bookings', 'stripe_transfer_id')) {
                $table->string('stripe_transfer_id', 128)->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_27_000006_add_contact_fields_to_bookings */
    private function fusion20260527000006AddContactFieldsToBookings(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'contact_name')) {
                $table->string('contact_name')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100014_add_missing_columns_to_bookings_table */
    private function fusion20260528100014AddMissingColumnsToBookingsTable(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            // FK references (->index)
            if (! Schema::hasColumn('bookings', 'assigned_provider_organization_id')) {
                $table->unsignedBigInteger('assigned_provider_organization_id')->nullable()->index();
            }
            if (! Schema::hasColumn('bookings', 'provider_team_id')) {
                $table->unsignedBigInteger('provider_team_id')->nullable()->index();
            }
            if (! Schema::hasColumn('bookings', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->index();
            }

            // Timestamps (datetime casts)
            if (! Schema::hasColumn('bookings', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'mission_arrived_at')) {
                $table->timestamp('mission_arrived_at')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'client_presence_confirmed_at')) {
                $table->timestamp('client_presence_confirmed_at')->nullable();
            }

            // JSON / array casts
            if (! Schema::hasColumn('bookings', 'options')) {
                $table->json('options')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'areas')) {
            }
            if (! Schema::hasColumn('bookings', 'photos_avant')) {
                $table->json('photos_avant')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'photos_apres')) {
                $table->json('photos_apres')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'terrain_checklist')) {
                $table->json('terrain_checklist')->nullable();
            }

            // String
            if (! Schema::hasColumn('bookings', 'contact_email')) {
                $table->string('contact_email')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100041_add_template_payload_to_recurring_booking_series_table */
    private function fusion20260528100041AddTemplatePayloadToRecurringBookingSeriesTabl(): void
    {
        if (! Schema::hasTable('recurring_booking_series')) {
            return;
        }

        Schema::table('recurring_booking_series', function (Blueprint $table) {
            if (! Schema::hasColumn('recurring_booking_series', 'template_payload')) {
                $table->json('template_payload')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_06_02_000001_add_provider_type_preference_to_bookings */
    private function fusion20260602000001AddProviderTypePreferenceToBookings(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'provider_type_preference')) {
                $table->string('provider_type_preference', 20)->default('any');
            }
        });
    }

    /** Fusionne depuis 2026_06_08_000002_add_date_indexes_to_bookings */
    private function fusion20260608000002AddDateIndexesToBookings(): void
    {
        if (! Schema::hasColumn('bookings', 'date')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! $this->fusion20260608000002AddDateIndexesToBookingsAideHasIndex('bookings', 'bookings_employe_id_date_index')) {
                $table->index(['employe_id', 'date'], 'bookings_employe_id_date_index');
            }
            if (! $this->fusion20260608000002AddDateIndexesToBookingsAideHasIndex('bookings', 'bookings_client_id_date_index')) {
                $table->index(['client_id', 'date'], 'bookings_client_id_date_index');
            }
        });
    }

    private function fusion20260608000002AddDateIndexesToBookingsAideHasIndex(string $table, string $index): bool
    {
        try {
            return collect(Schema::getIndexes($table))->contains(fn ($i) => $i['name'] === $index);
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Fusionne depuis 2026_08_01_000700_add_payment_plan_to_bookings */
    private function fusion20260801000700AddPaymentPlanToBookings(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'payment_plan')) {
                $table->string('payment_plan', 20)->nullable();
            }

            if (! Schema::hasColumn('bookings', 'deposit_payment_intent_id')) {
                $table->string('deposit_payment_intent_id')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'deposit_amount_cents')) {
                $table->unsignedInteger('deposit_amount_cents')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'deposit_captured_at')) {
                $table->timestamp('deposit_captured_at')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_08_14_090000_mode_client_absent_et_ping_de_mi_mission */
    private function fusion20260814090000ModeClientAbsentEtPingDeMiMission(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'client_absent')) {
                // Défaut à faux : la présence reste le cas normal, et le code à six chiffres la
                // preuve par défaut. Basculer l'ensemble sur la photo affaiblirait le dispositif
                // pour tout le monde afin de servir une minorité.
                $table->boolean('client_absent')->default(false);
            }

            if (! Schema::hasColumn('bookings', 'client_absent_instructions')) {
                $table->text('client_absent_instructions')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'backup_contact_name')) {
                $table->string('backup_contact_name')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'backup_contact_phone')) {
                $table->string('backup_contact_phone', 32)->nullable();
            }

            if (! Schema::hasColumn('bookings', 'checkin_ping_sent_at')) {
                $table->timestamp('checkin_ping_sent_at')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'checkin_ping_answer')) {
                // `ok` ou `probleme` : deux réponses, parce qu'une échelle de 1 à 10 au milieu
                // d'une intervention ne se remplit pas. La nuance viendra de l'avis, plus tard.
                $table->string('checkin_ping_answer', 16)->nullable();
            }

            if (! Schema::hasColumn('bookings', 'checkin_ping_answered_at')) {
                $table->timestamp('checkin_ping_answered_at')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_09_07_090000_porter_la_consigne_de_derniere_minute */
    private function fusion20260907090000PorterLaConsigneDeDerniereMinute(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'live_access_note')) {
                $table->text('live_access_note')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'live_access_note_at')) {
                $table->timestamp('live_access_note_at')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_09_08_090000_porter_le_minuteur_de_retard */
    private function fusion20260908090000PorterLeMinuteurDeRetard(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'late_notified_at')) {
                $table->timestamp('late_notified_at')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'provider_delay_eta_at')) {
                $table->timestamp('provider_delay_eta_at')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'provider_delay_reason')) {
                $table->string('provider_delay_reason', 180)->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_11_224500_fix_portal_and_booking_legacy_columns */
    private function fusionFixPortalAndBookingLegacyColumnsBookings(): void
    {
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

    /** Fusionne depuis 2026_05_13_194311_fix_brio_test_schema_compatibility_final */
    private function fusionFixBrioTestSchemaCompatibilityFinalBookings(): void
    {
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                if (! Schema::hasColumn('bookings', 'destination_lat')) {
                    $table->decimal('destination_lat', 10, 7)->nullable();
                }

                if (! Schema::hasColumn('bookings', 'destination_lng')) {
                    $table->decimal('destination_lng', 10, 7)->nullable();
                }

                if (! Schema::hasColumn('bookings', 'cancelled_by')) {
                    $table->unsignedBigInteger('cancelled_by')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'cancellation_reason')) {
                    $table->text('cancellation_reason')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'cancellation_fee_amount')) {
                    $table->decimal('cancellation_fee_amount', 10, 2)->default(0);
                }

                if (! Schema::hasColumn('bookings', 'cancellation_fee_percent')) {
                    $table->unsignedInteger('cancellation_fee_percent')->default(0);
                }

                if (! Schema::hasColumn('bookings', 'series_status')) {
                    $table->string('series_status')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'series_position')) {
                    $table->unsignedInteger('series_position')->nullable();
                }
            });
        }
    }

    /** Fusionne depuis 2026_05_13_225926_fix_approval_booking_runtime_schema_round4 */
    private function fusionFixApprovalBookingRuntimeSchemaRound4Bookings(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'asap_requested_at')) {
                $table->timestamp('asap_requested_at')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'asap_deadline_at')) {
                $table->timestamp('asap_deadline_at')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'matched_at')) {
                $table->timestamp('matched_at')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'matching_snapshot')) {
                $table->json('matching_snapshot')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'destination_lat')) {
                $table->decimal('destination_lat', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('bookings', 'destination_lng')) {
                $table->decimal('destination_lng', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('bookings', 'address_components')) {
                $table->json('address_components')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_06_01_000001_add_provider_org_team_to_booking_and_mission */
    private function fusionAddProviderOrgTeamToBookingAndMissionBookings(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'assigned_provider_organization_id')) {
                if (Schema::hasColumn('bookings', 'assigned_provider_user_id')) {
                    $table->foreignId('assigned_provider_organization_id')->nullable();
                } else {
                    $table->foreignId('assigned_provider_organization_id')->nullable();
                }
            }
            if (! Schema::hasColumn('bookings', 'provider_team_id')) {
                if (Schema::hasColumn('bookings', 'assigned_provider_organization_id')) {
                    $table->unsignedBigInteger('provider_team_id')->nullable();
                } else {
                    $table->unsignedBigInteger('provider_team_id')->nullable();
                }
            }
        });
    }

    /** Fusionne depuis 2026_06_05_000001_add_provider_org_and_contract_links_for_sp4 */
    private function fusionAddProviderOrgAndContractLinksForSp4Bookings(): void
    {
        if (Schema::hasTable('bookings') && ! Schema::hasColumn('bookings', 'organization_contract_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_contract_id')->nullable()->index();
            });
        }
    }

    /** Fusionne depuis 2026_06_10_000001_add_missing_referenced_columns */
    private function fusionAddMissingReferencedColumnsBookings(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'feedback_demande_envoye_at')) {
                $table->timestamp('feedback_demande_envoye_at')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'remarque_terrain')) {
                $table->text('remarque_terrain')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_08_30_090100_porter_les_heures_achetees */
    private function fusionPorterLesHeuresAcheteesBookings(): void
    {
        if (Schema::hasTable('bookings') && ! Schema::hasColumn('bookings', 'purchased_minutes')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->unsignedInteger('purchased_minutes')->nullable();
            });
        }
    }
};
