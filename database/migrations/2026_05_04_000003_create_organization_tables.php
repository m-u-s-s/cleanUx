<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->corpsInitial();
        $this->fusion20260508100001AddPresenceToProviderProfiles();
        $this->fusion20260509100003AddOnboardingToProviderProfiles();
        $this->fusion20260517140003AddRatingAggregatesToProviderProfiles();
        $this->fusion20260518140003AddKycFieldsToProviderProfiles();
        $this->fusion20260528100040AddMissingColumnsToProviderProfilesTable();
        $this->fusion20260603000001AddRatingToOrganizationAccounts();
        $this->fusion20260728000001AddSelfRegisteredAtToProviderProfiles();
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_profiles');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('organization_members');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'current_organization_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['current_organization_id']);
            });
        }

        Schema::dropIfExists('organization_accounts');
    }

    /** Le corps d origine, extrait pour que son `return` ne quitte que lui. */
    private function corpsInitial(): void
    {
        Schema::create('organization_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('slug')->unique();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('commune_id')->nullable();
            $table->unsignedBigInteger('postal_code_id')->nullable();

            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();

            $table->boolean('is_key_account')->default(false);

            // client_company, provider_company, provider_solo, hybrid.
            $table->string('type');

            // pending, active, suspended, archived.
            $table->string('status')->default('active');

            $table->string('tva_number')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->string('billing_email')->nullable();
            $table->string('billing_address')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_postal_code')->nullable();
            $table->string('billing_country', 2)->default('BE');

            $table->string('default_currency', 3)->default('EUR');

            // immediate, monthly, contract.
            $table->string('payment_terms')->default('immediate');

            $table->boolean('is_multisite')->default(false);
            $table->boolean('requires_internal_approval')->default(false);

            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('slug');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('current_organization_id')
                ->references('id')
                ->on('organization_accounts')
                ->nullOnDelete();
        });

        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_account_id')
                ->constrained('organization_accounts')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
             * Client company roles:
             * owner, manager, site_manager, finance, requester, viewer
             *
             * Provider company roles:
             * owner, operations_manager, dispatcher, team_lead, worker, quality_manager, finance, viewer
             */
            $table->string('role');

            $table->json('permissions')->nullable();

            // invited, active, suspended, left.
            $table->string('status')->default('active');

            $table->foreignId('invited_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();

            $table->timestamps();

            $table->unique(['organization_account_id', 'user_id']);
            $table->index(['organization_account_id', 'role']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            // personal = client normal / particulier.
            $table->string('customer_type')->default('personal');

            $table->string('default_phone')->nullable();
            $table->string('default_address')->nullable();
            $table->string('default_city')->nullable();
            $table->string('default_postal_code')->nullable();
            $table->string('default_country', 2)->default('BE');

            $table->string('plan_type')->default('standard');
            $table->string('plan_status')->default('inactive');
            $table->timestamp('premium_started_at')->nullable();
            $table->timestamp('premium_renewal_at')->nullable();

            $table->json('preferences')->nullable();

            $table->timestamps();

            $table->index(['customer_type', 'plan_type']);
        });

        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('organization_account_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->nullOnDelete();

            // independent, company_worker.
            $table->string('provider_type')->default('independent');

            // pending, active, suspended, rejected.
            $table->string('status')->default('pending');

            // unverified, pending, verified, rejected.
            $table->string('verification_status')->default('unverified');

            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();

            $table->integer('default_slot_duration')->default(90);

            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
            $table->timestamp('last_location_at')->nullable();

            $table->string('stripe_connect_account_id')->nullable();
            $table->string('stripe_connect_status')->default('not_connected');
            $table->timestamp('stripe_connect_onboarded_at')->nullable();

            $table->json('skills')->nullable();
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['provider_type', 'status']);
            $table->index(['status', 'verification_status']);
            $table->index('organization_account_id');
        });
    }

    /** Fusionne depuis 2026_05_08_100001_add_presence_to_provider_profiles */
    private function fusion20260508100001AddPresenceToProviderProfiles(): void
    {
        if (! Schema::hasTable('provider_profiles')) {
            return;
        }

        Schema::table('provider_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('provider_profiles', 'is_online')) {
                $table->boolean('is_online')->default(false);
            }
            if (! Schema::hasColumn('provider_profiles', 'went_online_at')) {
                $table->timestamp('went_online_at')->nullable();
            }
            if (! Schema::hasColumn('provider_profiles', 'went_offline_at')) {
                $table->timestamp('went_offline_at')->nullable();
            }
            if (! Schema::hasColumn('provider_profiles', 'last_heartbeat_at')) {
                $table->timestamp('last_heartbeat_at')->nullable();
            }
            if (! Schema::hasColumn('provider_profiles', 'presence_meta')) {
                $table->json('presence_meta')->nullable();
            }
        });

        // Index pour les requêtes "trouve les prestataires online proches"
        // dropIfExists d'abord pour idempotence
        Schema::table('provider_profiles', function (Blueprint $table) {
            try {
                $table->index(['is_online', 'last_heartbeat_at'], 'provider_profiles_online_heartbeat_idx');
            } catch (Throwable $e) {
                // Index existe déjà
            }
        });
    }

    /** Fusionne depuis 2026_05_09_100003_add_onboarding_to_provider_profiles */
    private function fusion20260509100003AddOnboardingToProviderProfiles(): void
    {
        if (! Schema::hasTable('provider_profiles')) {
            return;
        }

        Schema::table('provider_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('provider_profiles', 'onboarding_step')) {
                $table->unsignedTinyInteger('onboarding_step')->default(0);
            }
            if (! Schema::hasColumn('provider_profiles', 'onboarding_completed_at')) {
                $table->timestamp('onboarding_completed_at')->nullable();
            }
            if (! Schema::hasColumn('provider_profiles', 'bio')) {
                $table->text('bio')->nullable();
            }
            if (! Schema::hasColumn('provider_profiles', 'photo_path')) {
                $table->string('photo_path', 500)->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_17_140003_add_rating_aggregates_to_provider_profiles */
    private function fusion20260517140003AddRatingAggregatesToProviderProfiles(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('provider_profiles', 'rating_avg')) {
                $table->decimal('rating_avg', 3, 2)->nullable();
            }
            if (! Schema::hasColumn('provider_profiles', 'rating_count')) {
                $table->unsignedInteger('rating_count')->default(0);
            }
            if (! Schema::hasColumn('provider_profiles', 'rating_distribution')) {
                $table->json('rating_distribution')->nullable();
            }
            if (! Schema::hasColumn('provider_profiles', 'rating_dimensions')) {
                $table->json('rating_dimensions')->nullable();
            }
            if (! Schema::hasColumn('provider_profiles', 'rating_last_at')) {
                $table->timestamp('rating_last_at')->nullable();
            }
        });

        Schema::table('provider_profiles', function (Blueprint $table) {
            if (! $this->fusion20260517140003AddRatingAggregatesToProviderProfilesAideIndexExists('provider_profiles', 'provider_profiles_rating_avg_index')) {
                $table->index('rating_avg', 'provider_profiles_rating_avg_index');
            }
        });
    }

    private function fusion20260517140003AddRatingAggregatesToProviderProfilesAideIndexExists(string $table, string $name): bool
    {
        $conn = Schema::getConnection();
        $driver = $conn->getDriverName();

        try {
            if ($driver === 'mysql' || $driver === 'mariadb') {
                $rows = $conn->select(
                    'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
                    [$table, $name]
                );

                return count($rows) > 0;
            }
            if ($driver === 'sqlite') {
                $rows = $conn->select("PRAGMA index_list('{$table}')");
                foreach ($rows as $row) {
                    if (($row->name ?? null) === $name) {
                        return true;
                    }
                }

                return false;
            }
            if ($driver === 'pgsql') {
                $rows = $conn->select(
                    'SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                    [$table, $name]
                );

                return count($rows) > 0;
            }
        } catch (Throwable $e) {
            return false;
        }

        return false;
    }

    /** Fusionne depuis 2026_05_18_140003_add_kyc_fields_to_provider_profiles */
    private function fusion20260518140003AddKycFieldsToProviderProfiles(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            foreach ([
                'kyc_provider' => fn ($t) => $t->string('kyc_provider', 32)->nullable(),
                'kyc_external_applicant_id' => fn ($t) => $t->string('kyc_external_applicant_id', 128)->nullable(),
                'kyc_last_verification_id' => fn ($t) => $t->unsignedBigInteger('kyc_last_verification_id')->nullable(),
                'kyc_completed_at' => fn ($t) => $t->timestamp('kyc_completed_at')->nullable(),
                'kyc_score' => fn ($t) => $t->decimal('kyc_score', 4, 2)->nullable(),
            ] as $col => $builder) {
                if (! Schema::hasColumn('provider_profiles', $col)) {
                    $builder($table);
                }
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100040_add_missing_columns_to_provider_profiles_table */
    private function fusion20260528100040AddMissingColumnsToProviderProfilesTable(): void
    {
        if (! Schema::hasTable('provider_profiles')) {
            return;
        }

        Schema::table('provider_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('provider_profiles', 'onboarding_started_at')) {
                $table->timestamp('onboarding_started_at')->nullable();
            }
            if (! Schema::hasColumn('provider_profiles', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
            if (! Schema::hasColumn('provider_profiles', 'verification_notes')) {
                $table->text('verification_notes')->nullable();
            }
            if (! Schema::hasColumn('provider_profiles', 'battery_level')) {
                $table->unsignedInteger('battery_level')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_06_03_000001_add_rating_to_organization_accounts */
    private function fusion20260603000001AddRatingToOrganizationAccounts(): void
    {
        Schema::table('organization_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_accounts', 'rating_avg')) {
                $table->decimal('rating_avg', 3, 2)->nullable();
            }
            if (! Schema::hasColumn('organization_accounts', 'rating_count')) {
                $table->unsignedInteger('rating_count')->default(0);
            }
        });
    }

    /** Fusionne depuis 2026_07_28_000001_add_self_registered_at_to_provider_profiles */
    private function fusion20260728000001AddSelfRegisteredAtToProviderProfiles(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->timestamp('self_registered_at')->nullable();
        });
    }
};
