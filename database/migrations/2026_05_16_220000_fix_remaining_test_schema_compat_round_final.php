<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureCountryBillingProfileColumns();
        $this->ensureCountryOperationalSettingMarketStage();
        $this->ensureServicePartnerColumns();
        $this->ensureServicePartnerLoadSnapshots();
        $this->ensureFinanceDocumentColumns();
        $this->ensureUserAdditionalColumns();
        $this->ensureActivityLogColumns();
        $this->ensureLegacyRendezVousColumns();
        $this->ensureOrganizationContractColumns();
        $this->ensureMarketLaunchReadinessColumns();
        $this->ensureWorkOrderLineColumns();
        $this->ensureCountryServiceCatalogRuleColumns();
        $this->ensureFieldTeamColumns();
        $this->ensurePartnerZoneCoverages();
        $this->relaxFinanceDocumentForeignKeys();
    }

    private function ensureOrganizationContractColumns(): void
    {
        if (! Schema::hasTable('organization_contracts')) {
            return;
        }

        Schema::table('organization_contracts', function (Blueprint $table) {
            foreach (
                [
                    ['country_id', fn ($t) => $t->unsignedBigInteger('country_id')->nullable()->index()],
                    ['service_zone_id', fn ($t) => $t->unsignedBigInteger('service_zone_id')->nullable()->index()],
                    ['default_field_team_id', fn ($t) => $t->unsignedBigInteger('default_field_team_id')->nullable()],
                    ['default_service_partner_id', fn ($t) => $t->unsignedBigInteger('default_service_partner_id')->nullable()],
                    ['pricing_model', fn ($t) => $t->string('pricing_model')->nullable()],
                    ['billing_cycle', fn ($t) => $t->string('billing_cycle')->nullable()],
                    ['approval_mode', fn ($t) => $t->string('approval_mode')->nullable()],
                    ['requires_purchase_order', fn ($t) => $t->boolean('requires_purchase_order')->default(false)],
                    ['default_cost_center', fn ($t) => $t->string('default_cost_center')->nullable()],
                    ['negotiated_discount_percent', fn ($t) => $t->decimal('negotiated_discount_percent', 5, 2)->nullable()],
                    ['payment_terms_days', fn ($t) => $t->unsignedInteger('payment_terms_days')->nullable()],
                    ['sla_response_hours', fn ($t) => $t->unsignedInteger('sla_response_hours')->nullable()],
                    ['sla_resolution_hours', fn ($t) => $t->unsignedInteger('sla_resolution_hours')->nullable()],
                    ['effective_from', fn ($t) => $t->date('effective_from')->nullable()],
                    ['effective_to', fn ($t) => $t->date('effective_to')->nullable()],
                    ['contract_reference', fn ($t) => $t->string('contract_reference')->nullable()],
                    ['notes', fn ($t) => $t->text('notes')->nullable()],
                ] as [$column, $creator]
            ) {
                if (! Schema::hasColumn('organization_contracts', $column)) {
                    $creator($table);
                }
            }
        });
    }

    private function ensureWorkOrderLineColumns(): void
    {
        if (! Schema::hasTable('work_order_lines')) {
            return;
        }

        Schema::table('work_order_lines', function (Blueprint $table) {
            foreach (
                [
                    ['title', fn ($t) => $t->string('title')->nullable()],
                    ['description', fn ($t) => $t->text('description')->nullable()],
                    ['quantity', fn ($t) => $t->decimal('quantity', 10, 2)->default(1)],
                    ['unit', fn ($t) => $t->string('unit')->nullable()],
                    ['unit_price', fn ($t) => $t->decimal('unit_price', 10, 2)->default(0)],
                    ['line_total', fn ($t) => $t->decimal('line_total', 10, 2)->default(0)],
                    ['surface_value', fn ($t) => $t->decimal('surface_value', 10, 2)->nullable()],
                ] as [$column, $creator]
            ) {
                if (! Schema::hasColumn('work_order_lines', $column)) {
                    $creator($table);
                }
            }
        });
    }

    private function ensureCountryServiceCatalogRuleColumns(): void
    {
        if (! Schema::hasTable('country_service_catalog_rules')) {
            return;
        }

        Schema::table('country_service_catalog_rules', function (Blueprint $table) {
            foreach (
                [
                    ['sla_response_hours', fn ($t) => $t->unsignedInteger('sla_response_hours')->nullable()],
                    ['sla_resolution_hours', fn ($t) => $t->unsignedInteger('sla_resolution_hours')->nullable()],
                    ['default_team_id', fn ($t) => $t->unsignedBigInteger('default_team_id')->nullable()],
                    ['default_partner_id', fn ($t) => $t->unsignedBigInteger('default_partner_id')->nullable()],
                    ['pricing_multiplier', fn ($t) => $t->decimal('pricing_multiplier', 8, 2)->default(1)],
                    ['requires_quote', fn ($t) => $t->boolean('requires_quote')->default(false)],
                    ['requires_manual_validation', fn ($t) => $t->boolean('requires_manual_validation')->default(false)],
                    ['minimum_notice_hours', fn ($t) => $t->unsignedInteger('minimum_notice_hours')->default(24)],
                ] as [$column, $creator]
            ) {
                if (! Schema::hasColumn('country_service_catalog_rules', $column)) {
                    $creator($table);
                }
            }
        });
    }

    private function ensureFieldTeamColumns(): void
    {
        if (Schema::hasTable('field_teams')) {
            Schema::table('field_teams', function (Blueprint $table) {
                if (! Schema::hasColumn('field_teams', 'service_partner_id')) {
                    $table->unsignedBigInteger('service_partner_id')->nullable()->index();
                }
                if (! Schema::hasColumn('field_teams', 'organization_account_id')) {
                    $table->unsignedBigInteger('organization_account_id')->nullable()->index();
                }
                if (! Schema::hasColumn('field_teams', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (! Schema::hasColumn('field_teams', 'slug')) {
                    $table->string('slug')->nullable();
                }
            });

            // Best-effort : la colonne existe déjà avec NOT NULL DEFAULT 3 dans
            // une migration antérieure, mais certains formulaires Livewire
            // envoient explicitement null. On la repasse en nullable.
            try {
                Schema::table('field_teams', function (Blueprint $table) {
                    $table->unsignedInteger('max_concurrent_missions')->nullable()->default(3)->change();
                });
            } catch (\Throwable $e) {
                // ignore — change() requiert doctrine/dbal sur certains drivers.
            }
        }

        if (Schema::hasTable('field_team_members')) {
            Schema::table('field_team_members', function (Blueprint $table) {
                if (! Schema::hasColumn('field_team_members', 'left_at')) {
                    $table->timestamp('left_at')->nullable();
                }
                if (! Schema::hasColumn('field_team_members', 'role_on_team')) {
                    $table->string('role_on_team')->nullable();
                }
                if (! Schema::hasColumn('field_team_members', 'is_team_lead')) {
                    $table->boolean('is_team_lead')->default(false);
                }
                if (! Schema::hasColumn('field_team_members', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (! Schema::hasColumn('field_team_members', 'joined_at')) {
                    $table->timestamp('joined_at')->nullable();
                }
            });
        }
    }

    private function ensurePartnerZoneCoverages(): void
    {
        if (Schema::hasTable('partner_zone_coverages')) {
            return;
        }

        Schema::create('partner_zone_coverages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_partner_id')->index();
            $table->unsignedBigInteger('service_zone_id')->nullable()->index();
            $table->unsignedBigInteger('country_id')->nullable()->index();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('coverage_priority')->default(100);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['service_partner_id', 'service_zone_id'], 'partner_zone_coverage_unique');
        });
    }

    private function ensureMarketLaunchReadinessColumns(): void
    {
        if (! Schema::hasTable('market_launch_readiness')) {
            return;
        }

        Schema::table('market_launch_readiness', function (Blueprint $table) {
            foreach (
                [
                    ['catalog_ready', fn ($t) => $t->boolean('catalog_ready')->default(false)],
                    ['booking_ready', fn ($t) => $t->boolean('booking_ready')->default(false)],
                    ['mission_ready', fn ($t) => $t->boolean('mission_ready')->default(false)],
                    ['billing_ready', fn ($t) => $t->boolean('billing_ready')->default(false)],
                    ['partner_network_ready', fn ($t) => $t->boolean('partner_network_ready')->default(false)],
                    ['compliance_ready', fn ($t) => $t->boolean('compliance_ready')->default(false)],
                    ['support_ready', fn ($t) => $t->boolean('support_ready')->default(false)],
                    ['notes', fn ($t) => $t->text('notes')->nullable()],
                    ['last_audited_at', fn ($t) => $t->timestamp('last_audited_at')->nullable()],
                ] as [$column, $creator]
            ) {
                if (! Schema::hasColumn('market_launch_readiness', $column)) {
                    $creator($table);
                }
            }
        });
    }

    private function ensureLegacyRendezVousColumns(): void
    {
        if (! Schema::hasTable('rendez_vous')) {
            return;
        }

        Schema::table('rendez_vous', function (Blueprint $table) {
            if (! Schema::hasColumn('rendez_vous', 'postal_code_id')) {
                $table->unsignedBigInteger('postal_code_id')->nullable()->index();
            }
            if (! Schema::hasColumn('rendez_vous', 'ville')) {
                $table->string('ville')->nullable();
            }
            if (! Schema::hasColumn('rendez_vous', 'code_postal')) {
                $table->string('code_postal')->nullable();
            }
            if (! Schema::hasColumn('rendez_vous', 'booking_reference')) {
                $table->string('booking_reference')->nullable()->index();
            }
            if (! Schema::hasColumn('rendez_vous', 'zone_snapshot')) {
                $table->json('zone_snapshot')->nullable();
            }
            if (! Schema::hasColumn('rendez_vous', 'pricing_snapshot')) {
                $table->json('pricing_snapshot')->nullable();
            }
        });
    }

    private function ensureActivityLogColumns(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_logs', 'severity')) {
                $table->string('severity', 30)->nullable()->index();
            }
            if (! Schema::hasColumn('activity_logs', 'is_critical')) {
                $table->boolean('is_critical')->default(false)->index();
            }
            if (! Schema::hasColumn('activity_logs', 'request_id')) {
                $table->string('request_id')->nullable()->index();
            }
            if (! Schema::hasColumn('activity_logs', 'service_zone_id')) {
                $table->unsignedBigInteger('service_zone_id')->nullable()->index();
            }
        });
    }

    private function ensureUserAdditionalColumns(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'stripe_connect_status')) {
                $table->string('stripe_connect_status')->nullable();
            }
            if (! Schema::hasColumn('users', 'stripe_connect_account_id')) {
                $table->string('stripe_connect_account_id')->nullable();
            }
            if (! Schema::hasColumn('users', 'is_super_admin')) {
                $table->boolean('is_super_admin')->default(false);
            }
            if (! Schema::hasColumn('users', 'admin_permissions')) {
                $table->json('admin_permissions')->nullable();
            }
        });
    }

    private function ensureFinanceDocumentColumns(): void
    {
        if (Schema::hasTable('finance_invoices')) {
            Schema::table('finance_invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('finance_invoices', 'finance_quote_id')) {
                    $table->unsignedBigInteger('finance_quote_id')->nullable()->index();
                }
                if (! Schema::hasColumn('finance_invoices', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('finance_payments')) {
            Schema::table('finance_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('finance_payments', 'payment_reference')) {
                    $table->string('payment_reference')->nullable()->index();
                }
                if (! Schema::hasColumn('finance_payments', 'method')) {
                    $table->string('method')->nullable();
                }
                if (! Schema::hasColumn('finance_payments', 'external_reference')) {
                    $table->string('external_reference')->nullable();
                }
                if (! Schema::hasColumn('finance_payments', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (! Schema::hasColumn('finance_payments', 'meta')) {
                    $table->json('meta')->nullable();
                }
            });
        }

        if (Schema::hasTable('finance_quotes')) {
            Schema::table('finance_quotes', function (Blueprint $table) {
                if (! Schema::hasColumn('finance_quotes', 'tax_rate')) {
                    $table->decimal('tax_rate', 6, 2)->default(0);
                }
                if (! Schema::hasColumn('finance_quotes', 'issued_at')) {
                    $table->timestamp('issued_at')->nullable();
                }
                if (! Schema::hasColumn('finance_quotes', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable();
                }
                if (! Schema::hasColumn('finance_quotes', 'snapshot')) {
                    $table->json('snapshot')->nullable();
                }
                if (! Schema::hasColumn('finance_quotes', 'meta')) {
                    $table->json('meta')->nullable();
                }
            });
        }
    }

    private function relaxFinanceDocumentForeignKeys(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        foreach (['finance_quotes', 'finance_invoices'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $columns = Schema::getColumnListing($tableName);
            if (! in_array('rendez_vous_id', $columns, true)) {
                continue;
            }

            if ($driver === 'sqlite') {
                $this->rebuildSqliteTableWithoutRendezVousFk($tableName);
            } else {
                try {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->dropForeign(['rendez_vous_id']);
                    });
                } catch (\Throwable $e) {
                    // ignore : already dropped or never existed
                }
            }
        }
    }

    private function rebuildSqliteTableWithoutRendezVousFk(string $tableName): void
    {
        $conn = Schema::getConnection();
        $current = $conn->selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$tableName]);

        if (! $current || ! isset($current->sql)) {
            return;
        }

        $sql = (string) $current->sql;

        if (! str_contains($sql, 'rendez_vous')) {
            return;
        }

        // Strip the FK clause "foreign key("rendez_vous_id") references "rendez_vous"("id") on delete ..."
        $pattern = '/,\s*foreign\s+key\(\"rendez_vous_id\"\)\s+references\s+\"rendez_vous\"\(\"id\"\)(\s+on\s+delete\s+[a-z_ ]+)?/i';
        $newSql = preg_replace($pattern, '', $sql);

        if ($newSql === null || $newSql === $sql) {
            return;
        }

        // SQLite needs to recreate the table.
        $tmpName = $tableName . '_legacy_fk_tmp';
        $createTmp = preg_replace('/CREATE\s+TABLE\s+\"' . preg_quote($tableName, '/') . '\"/i', 'CREATE TABLE "' . $tmpName . '"', $newSql, 1);

        $conn->statement('PRAGMA foreign_keys = OFF');
        $conn->statement($createTmp);
        $conn->statement(sprintf('INSERT INTO "%s" SELECT * FROM "%s"', $tmpName, $tableName));
        $conn->statement(sprintf('DROP TABLE "%s"', $tableName));
        $conn->statement(sprintf('ALTER TABLE "%s" RENAME TO "%s"', $tmpName, $tableName));
        $conn->statement('PRAGMA foreign_keys = ON');
    }

    private function ensureCountryBillingProfileColumns(): void
    {
        if (! Schema::hasTable('country_billing_profiles')) {
            return;
        }

        Schema::table('country_billing_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('country_billing_profiles', 'payment_terms_days')) {
                $table->unsignedInteger('payment_terms_days')->default(30);
            }
            if (! Schema::hasColumn('country_billing_profiles', 'quote_validity_days')) {
                $table->unsignedInteger('quote_validity_days')->default(30);
            }
            if (! Schema::hasColumn('country_billing_profiles', 'rounding_mode')) {
                $table->string('rounding_mode', 30)->default('half_up');
            }
            if (! Schema::hasColumn('country_billing_profiles', 'decimal_separator')) {
                $table->string('decimal_separator', 5)->default(',');
            }
            if (! Schema::hasColumn('country_billing_profiles', 'thousands_separator')) {
                $table->string('thousands_separator', 5)->default(' ');
            }
            if (! Schema::hasColumn('country_billing_profiles', 'currency_position')) {
                $table->string('currency_position', 20)->default('after');
            }
            if (! Schema::hasColumn('country_billing_profiles', 'currency_code')) {
                $table->string('currency_code', 3)->default('EUR');
            }
            if (! Schema::hasColumn('country_billing_profiles', 'currency_symbol')) {
                $table->string('currency_symbol', 8)->default('€');
            }
            if (! Schema::hasColumn('country_billing_profiles', 'prices_include_tax')) {
                $table->boolean('prices_include_tax')->default(false);
            }
            if (! Schema::hasColumn('country_billing_profiles', 'date_format')) {
                $table->string('date_format', 30)->default('d/m/Y');
            }
            if (! Schema::hasColumn('country_billing_profiles', 'time_format')) {
                $table->string('time_format', 30)->default('H:i');
            }
        });
    }

    private function ensureCountryOperationalSettingMarketStage(): void
    {
        if (! Schema::hasTable('country_operational_settings')) {
            return;
        }

        Schema::table('country_operational_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('country_operational_settings', 'market_stage')) {
                $table->string('market_stage', 60)->default('legacy');
            }
            if (! Schema::hasColumn('country_operational_settings', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (! Schema::hasColumn('country_operational_settings', 'timezone')) {
                $table->string('timezone')->nullable();
            }
            if (! Schema::hasColumn('country_operational_settings', 'settings')) {
                $table->json('settings')->nullable();
            }
        });
    }

    private function ensureServicePartnerColumns(): void
    {
        if (! Schema::hasTable('service_partners')) {
            return;
        }

        Schema::table('service_partners', function (Blueprint $table) {
            if (! Schema::hasColumn('service_partners', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (! Schema::hasColumn('service_partners', 'legal_name')) {
                $table->string('legal_name')->nullable();
            }
            if (! Schema::hasColumn('service_partners', 'daily_capacity')) {
                $table->unsignedInteger('daily_capacity')->default(8);
            }
            if (! Schema::hasColumn('service_partners', 'billing_email')) {
                $table->string('billing_email')->nullable();
            }
            if (! Schema::hasColumn('service_partners', 'quality_score')) {
                $table->decimal('quality_score', 5, 2)->nullable();
            }
            if (! Schema::hasColumn('service_partners', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    private function ensureServicePartnerLoadSnapshots(): void
    {
        if (Schema::hasTable('service_partner_load_snapshots')) {
            return;
        }

        Schema::create('service_partner_load_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_partner_id')->nullable()->index();
            $table->date('snapshot_date')->index();
            $table->unsignedInteger('active_missions_count')->default(0);
            $table->unsignedInteger('planned_segments_count')->default(0);
            $table->unsignedInteger('planned_minutes')->default(0);
            $table->unsignedInteger('daily_capacity')->default(0);
            $table->decimal('utilization_percent', 6, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['service_partner_id', 'snapshot_date'], 'service_partner_load_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_partner_load_snapshots');
    }
};
