<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->corpsInitial();
        $this->fusion20260517130006AddReferralCodeToUsersTable();
        $this->fusion20260518150002AddGdprFieldsToUsersTable();
        $this->fusion20260520020001AddTenantIdToUsersOptional();
        $this->fusion20260526000001AddThemePreferenceToUsers();
        $this->fusion20260608000003DropTenantIdFromUsers();
        $this->fusion20260612000002AddPhoneVerifiedAtToUsers();
        $this->fusionFixPortalAndBookingLegacyColumnsUsers();
        $this->fusionAddCurrencyPreferencesUsers();
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }

    /** Le corps d origine, extrait pour que son `return` ne quitte que lui. */
    private function corpsInitial(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            $table->string('role')->default('client');
            $table->string('account_type')->default('client_personal');

            $table->string('tva_number')->nullable();
            $table->unsignedInteger('duree_creneau')->default(90);

            $table->string('plan_type')->default('standard');
            $table->string('plan_status')->default('inactive');

            $table->unsignedBigInteger('organization_account_id')->nullable();

            $table->unsignedBigInteger('postal_code_id')->nullable();
            $table->unsignedBigInteger('primary_service_zone_id')->nullable();

            $table->json('permissions')->nullable();
            $table->string('access_scope')->default('own');
            $table->json('metadata')->nullable();

            $table->string('stripe_id')->nullable();
            $table->timestamp('premium_started_at')->nullable();
            $table->timestamp('premium_renewal_at')->nullable();

            $table->string('phone')->nullable();
            $table->string('locale', 8)->default('fr');
            $table->string('timezone')->default('Europe/Brussels');

            // Rôle global Brio uniquement : user, admin, super_admin.
            $table->string('platform_role')->default('user');

            // invited, active, suspended, disabled.
            $table->string('status')->default('active');
            $table->boolean('is_active')->default(true);

            // Jetstream / Fortify.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->rememberToken();

            $table->foreignId('current_team_id')->nullable();
            $table->foreignId('current_organization_id')->nullable();

            $table->string('profile_photo_path', 2048)->nullable();

            // Les jalons du raccordement Stripe, fusionnes depuis 2026_07_29_000002_add_stripe_connect_timestamps_to_users.
            $table->timestamp('stripe_connect_onboarded_at')->nullable();
            $table->timestamp('stripe_connect_charges_enabled_at')->nullable();
            $table->timestamp('stripe_connect_payouts_enabled_at')->nullable();
            $table->timestamps();

            $table->index(['platform_role', 'status']);
            $table->index('is_active');
            $table->index('current_organization_id');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->foreignId('user_id')
                ->nullable()
                ->index()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();

                $table->morphs('tokenable');

                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();

                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();

                $table->timestamps();
            });
        }

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    /** Fusionne depuis 2026_05_17_130006_add_referral_code_to_users_table */
    private function fusion20260517130006AddReferralCodeToUsersTable(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 32)->nullable();
            }

            if (! Schema::hasColumn('users', 'referred_by_referral_id')) {
                $table->unsignedBigInteger('referred_by_referral_id')->nullable();
            }
        });

        if (! $this->fusion20260517130006AddReferralCodeToUsersTableAideIndexExists('users', 'users_referral_code_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('referral_code', 'users_referral_code_unique');
            });
        }
    }

    private function fusion20260517130006AddReferralCodeToUsersTableAideIndexExists(string $table, string $name): bool
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

    /** Fusionne depuis 2026_05_18_150002_add_gdpr_fields_to_users_table */
    private function fusion20260518150002AddGdprFieldsToUsersTable(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'anonymized_at' => fn ($t) => $t->timestamp('anonymized_at')->nullable(),
                'processing_restricted_at' => fn ($t) => $t->timestamp('processing_restricted_at')->nullable(),
                'deletion_scheduled_at' => fn ($t) => $t->timestamp('deletion_scheduled_at')->nullable(),
                'last_gdpr_action_at' => fn ($t) => $t->timestamp('last_gdpr_action_at')->nullable(),
            ] as $col => $builder) {
                if (! Schema::hasColumn('users', $col)) {
                    $builder($table);
                }
            }
        });

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->index('anonymized_at', 'users_anonymized_at_index');
                $table->index('deletion_scheduled_at', 'users_deletion_scheduled_at_index');
            });
        } catch (Throwable $e) {
            // index existant
        }
    }

    /** Fusionne depuis 2026_05_20_020001_add_tenant_id_to_users_optional */
    private function fusion20260520020001AddTenantIdToUsersOptional(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        if (! Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->index('tenant_id');
            });
        }
    }

    /** Fusionne depuis 2026_05_26_000001_add_theme_preference_to_users */
    private function fusion20260526000001AddThemePreferenceToUsers(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme_preference', 10)->default('system');
        });
    }

    /** Fusionne depuis 2026_06_08_000003_drop_tenant_id_from_users */
    private function fusion20260608000003DropTenantIdFromUsers(): void
    {
        if (! Schema::hasColumn('users', 'tenant_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if ($this->fusion20260608000003DropTenantIdFromUsersAideHasIndex('users', 'users_tenant_id_index')) {
                $table->dropIndex('users_tenant_id_index');
            }
            $table->dropColumn('tenant_id');
        });
    }

    private function fusion20260608000003DropTenantIdFromUsersAideHasIndex(string $table, string $index): bool
    {
        try {
            return collect(Schema::getIndexes($table))->contains(fn ($i) => $i['name'] === $index);
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Fusionne depuis 2026_06_12_000002_add_phone_verified_at_to_users */
    private function fusion20260612000002AddPhoneVerifiedAtToUsers(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_11_224500_fix_portal_and_booking_legacy_columns */
    private function fusionFixPortalAndBookingLegacyColumnsUsers(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'managed_service_zone_id')) {
                    $table->unsignedBigInteger('managed_service_zone_id')->nullable()->index();
                }
            });
        }
    }

    /** Fusionne depuis 2026_05_07_140002_add_currency_preferences */
    private function fusionAddCurrencyPreferencesUsers(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'preferred_currency')) {
                $table->string('preferred_currency', 3)->nullable();
            }
        });
    }
};
