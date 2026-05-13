<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->restoreLegacyRendezVous();
        $this->restoreLegacyFeedback();
        $this->restoreFinanceAdvancedTables();
        $this->restoreEnterpriseWorkOrders();
        $this->restoreFieldTeams();
        $this->restoreLegacyParametres();
        // ---------------------------------------------------------------------
        // Compatibilité legacy/tests : colonnes et tables attendues par les
        // anciens écrans, factories et tests avancés.
        // ---------------------------------------------------------------------

        $addColumn = function (string $tableName, string $columnName, callable $callback): void {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, $columnName)) {
                Schema::table($tableName, function (Blueprint $table) use ($callback) {
                    $callback($table);
                });
            }
        };

        // platform_modules
        $addColumn('platform_modules', 'category', fn(Blueprint $table) => $table->string('category')->default('core'));
        $addColumn('platform_modules', 'rollout_strategy', fn(Blueprint $table) => $table->string('rollout_strategy')->default('global'));
        $addColumn('platform_modules', 'is_locked', fn(Blueprint $table) => $table->boolean('is_locked')->default(false));
        $addColumn('platform_modules', 'sort_order', fn(Blueprint $table) => $table->unsignedInteger('sort_order')->default(0));
        $addColumn('platform_modules', 'audience_rules', fn(Blueprint $table) => $table->json('audience_rules')->nullable());

        // email_logs
        $addColumn('email_logs', 'previewed_by_user_id', fn(Blueprint $table) => $table->unsignedBigInteger('previewed_by_user_id')->nullable());
        $addColumn('email_logs', 'context', fn(Blueprint $table) => $table->json('context')->nullable());

        // service_catalogs
        $addColumn('service_catalogs', 'settings', fn(Blueprint $table) => $table->json('settings')->nullable());

        // employee_zone_assignments
        $addColumn('employee_zone_assignments', 'assignment_type', fn(Blueprint $table) => $table->string('assignment_type')->default('primary'));
        $addColumn('employee_zone_assignments', 'coverage_priority', fn(Blueprint $table) => $table->unsignedInteger('coverage_priority')->default(0));
        $addColumn('employee_zone_assignments', 'starts_at', fn(Blueprint $table) => $table->timestamp('starts_at')->nullable());
        $addColumn('employee_zone_assignments', 'ends_at', fn(Blueprint $table) => $table->timestamp('ends_at')->nullable());
        $addColumn('employee_zone_assignments', 'notes', fn(Blueprint $table) => $table->text('notes')->nullable());

        // Pivot service_zone_postal_code
        if (! Schema::hasTable('service_zone_postal_code')) {
            Schema::create('service_zone_postal_code', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('service_zone_id');
                $table->unsignedBigInteger('postal_code_id');
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['service_zone_id', 'postal_code_id'], 'sz_pc_unique');
                $table->index('service_zone_id');
                $table->index('postal_code_id');
            });
        }

        // service_zone_postal_code : colonne attendue par les tests zone-aware
        $addColumn('service_zone_postal_code', 'is_primary', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false);
        });

        // bookings : colonne legacy attendue par RendezVousFactory / policy tests
        $addColumn('bookings', 'postal_code_id', function (Blueprint $table) {
            $table->unsignedBigInteger('postal_code_id')->nullable();
        });

        // Team lead operations center : table attendue par l'écran opérations
        if (! Schema::hasTable('mission_task_segments')) {
            Schema::create('mission_task_segments', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('mission_id')->nullable();
                $table->unsignedBigInteger('mission_batch_id')->nullable();
                $table->unsignedBigInteger('field_team_id')->nullable();
                $table->unsignedBigInteger('assigned_user_id')->nullable();

                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->string('segment_type')->default('standard');
                $table->string('status')->default('pending');

                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('planned_start_at')->nullable();
                $table->timestamp('planned_end_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['mission_id', 'status']);
                $table->index(['mission_batch_id', 'status']);
                $table->index(['field_team_id', 'status']);
                $table->index(['assigned_user_id', 'status']);
            });
        }

        // complaint_cases
        if (! Schema::hasTable('complaint_cases')) {
            Schema::create('complaint_cases', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->string('category')->nullable();
                $table->string('priority')->default('normal');
                $table->string('sla_policy')->nullable();
                $table->string('status')->default('open');
                $table->string('subject')->nullable();
                $table->text('description')->nullable();
                $table->json('attachments')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['client_id', 'status']);
                $table->index(['status', 'priority']);
            });
        }

        // incident_reports
        if (! Schema::hasTable('incident_reports')) {
            Schema::create('incident_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rendez_vous_id')->nullable();
                $table->unsignedBigInteger('employe_id')->nullable();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('organization_account_id')->nullable();

                $table->string('type')->default('incident');
                $table->string('priority')->default('normal');
                $table->string('sla_policy')->nullable();
                $table->string('severity')->default('normal');
                $table->string('status')->default('ouvert');

                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->text('location_notes')->nullable();
                $table->json('attachments')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->json('meta')->nullable();

                $table->timestamps();

                $table->index(['employe_id', 'status']);
                $table->index(['client_id', 'status']);
                $table->index(['status', 'priority']);
            });
        }

        // field teams / mission batches
        if (! Schema::hasTable('field_teams')) {
            Schema::create('field_teams', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('status')->default('active');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('field_team_members')) {
            Schema::create('field_team_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('field_team_id');
                $table->unsignedBigInteger('user_id');
                $table->boolean('is_team_lead')->default(false);
                $table->string('role')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['field_team_id', 'user_id']);
                $table->index(['user_id', 'is_team_lead']);
            });
        }

        if (! Schema::hasTable('mission_batches')) {
            Schema::create('mission_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('field_team_id')->nullable();
                $table->unsignedBigInteger('team_lead_user_id')->nullable();
                $table->string('name')->nullable();
                $table->string('status')->default('planned');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['team_lead_user_id', 'status']);
                $table->index(['field_team_id', 'status']);
                $table->index('start_date');
            });
        }
    }

    private function restoreLegacyRendezVous(): void
    {
        if (! Schema::hasTable('rendez_vous')) {
            Schema::create('rendez_vous', function (Blueprint $table) {
                $table->id();

                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('employe_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('service_catalog_id')->nullable()->constrained('service_catalogs')->nullOnDelete();
                $table->foreignId('service_zone_id')->nullable()->constrained('service_zones')->nullOnDelete();

                $table->string('status')->default('pending')->index();
                $table->string('type_lieu')->nullable();
                $table->string('frequence')->nullable();
                $table->string('priorite')->nullable();

                $table->string('place_type')->nullable();
                $table->string('frequency')->nullable();
                $table->string('priority')->nullable();

                $table->date('date')->nullable();
                $table->time('heure')->nullable();
                $table->timestamp('scheduled_at')->nullable();

                $table->string('adresse')->nullable();
                $table->string('address')->nullable();
                $table->string('city')->nullable();
                $table->string('postal_code')->nullable();

                $table->unsignedInteger('surface_m2')->nullable();
                $table->text('description')->nullable();
                $table->text('notes')->nullable();

                $table->decimal('estimated_price', 10, 2)->nullable();
                $table->decimal('final_price', 10, 2)->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['status', 'scheduled_at']);
            });
        }
    }

    private function restoreLegacyFeedback(): void
    {
        if (! Schema::hasTable('feedback')) {
            Schema::create('feedback', function (Blueprint $table) {
                $table->id();

                $table->foreignId('rendez_vous_id')->nullable()->constrained('rendez_vous')->nullOnDelete();
                $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
                $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
                $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('employe_id')->nullable()->constrained('users')->nullOnDelete();

                $table->unsignedTinyInteger('note')->nullable();
                $table->unsignedTinyInteger('rating')->nullable();
                $table->text('commentaire')->nullable();
                $table->text('comment')->nullable();
                $table->string('status')->default('published');

                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    private function restoreLegacyParametres(): void
    {
        if (! Schema::hasTable('parametres')) {
            Schema::create('parametres', function (Blueprint $table) {
                $table->id();

                $table->string('cle')->unique();
                $table->text('valeur')->nullable();

                // Compatibilité avec d'autres versions du projet
                $table->string('type')->nullable();
                $table->string('groupe')->nullable();
                $table->string('category')->nullable();

                $table->text('description')->nullable();
                $table->boolean('is_public')->default(false);
                $table->boolean('is_editable')->default(true);

                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    private function restoreFinanceAdvancedTables(): void
    {
        if (! Schema::hasTable('finance_quotes')) {
            Schema::create('finance_quotes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_account_id')->nullable()->constrained('organization_accounts')->nullOnDelete();
                $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
                $table->foreignId('rendez_vous_id')->nullable()->constrained('rendez_vous')->nullOnDelete();

                $table->string('quote_number')->nullable()->unique();
                $table->string('status')->default('draft');
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('tax_amount', 10, 2)->default(0);
                $table->decimal('total_amount', 10, 2)->default(0);
                $table->string('currency', 3)->default('EUR');
                $table->date('valid_until')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('finance_invoices')) {
            Schema::create('finance_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_account_id')->nullable()->constrained('organization_accounts')->nullOnDelete();
                $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
                $table->foreignId('rendez_vous_id')->nullable()->constrained('rendez_vous')->nullOnDelete();

                $table->string('invoice_number')->nullable()->unique();
                $table->string('status')->default('draft');
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('tax_amount', 10, 2)->default(0);
                $table->decimal('total_amount', 10, 2)->default(0);
                $table->decimal('paid_amount', 10, 2)->default(0);
                $table->string('currency', 3)->default('EUR');
                $table->date('issued_at')->nullable();
                $table->date('due_at')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('finance_payments')) {
            Schema::create('finance_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('finance_invoice_id')->nullable()->constrained('finance_invoices')->nullOnDelete();
                $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();

                $table->string('provider')->nullable();
                $table->string('provider_reference')->nullable();
                $table->string('status')->default('pending');
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('currency', 3)->default('EUR');
                $table->timestamp('paid_at')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('finance_reminders')) {
            Schema::create('finance_reminders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('finance_invoice_id')->nullable()->constrained('finance_invoices')->nullOnDelete();

                $table->string('level')->default('friendly');
                $table->string('status')->default('pending');
                $table->timestamp('sent_at')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    private function restoreEnterpriseWorkOrders(): void
    {
        if (! Schema::hasTable('enterprise_work_orders')) {
            Schema::create('enterprise_work_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_account_id')->nullable()->constrained('organization_accounts')->nullOnDelete();
                $table->foreignId('organization_site_id')->nullable()->constrained('organization_sites')->nullOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

                $table->string('reference')->nullable()->unique();
                $table->string('title')->nullable();
                $table->string('status')->default('draft');
                $table->string('priority')->default('normal');
                $table->date('requested_date')->nullable();
                $table->decimal('estimated_total', 10, 2)->default(0);

                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('work_order_lines')) {
            Schema::create('work_order_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('enterprise_work_order_id')->nullable()->constrained('enterprise_work_orders')->cascadeOnDelete();
                $table->foreignId('service_catalog_id')->nullable()->constrained('service_catalogs')->nullOnDelete();

                $table->string('description')->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 10, 2)->default(0);
                $table->decimal('total_price', 10, 2)->default(0);

                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('work_order_approvals')) {
            Schema::create('work_order_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('enterprise_work_order_id')->nullable()->constrained('enterprise_work_orders')->cascadeOnDelete();
                $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();

                $table->string('status')->default('pending');
                $table->text('comment')->nullable();
                $table->timestamp('decided_at')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    private function restoreFieldTeams(): void
    {
        if (! Schema::hasTable('field_teams')) {
            Schema::create('field_teams', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_account_id')->nullable()->constrained('organization_accounts')->nullOnDelete();
                $table->foreignId('service_zone_id')->nullable()->constrained('service_zones')->nullOnDelete();

                $table->string('name');
                $table->string('status')->default('active');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('field_team_members')) {
            Schema::create('field_team_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('field_team_id')->nullable()->constrained('field_teams')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->string('role')->default('worker');
                $table->string('status')->default('active');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employee_zone_assignments')) {
            Schema::create('employee_zone_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->foreignId('service_zone_id')->nullable()->constrained('service_zones')->cascadeOnDelete();

                $table->boolean('is_primary')->default(false);
                $table->string('status')->default('active');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'service_zone_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');
        Schema::dropIfExists('employee_zone_assignments');
        Schema::dropIfExists('field_team_members');
        Schema::dropIfExists('field_teams');

        Schema::dropIfExists('work_order_approvals');
        Schema::dropIfExists('work_order_lines');
        Schema::dropIfExists('enterprise_work_orders');

        Schema::dropIfExists('finance_reminders');
        Schema::dropIfExists('finance_payments');
        Schema::dropIfExists('finance_invoices');
        Schema::dropIfExists('finance_quotes');

        Schema::dropIfExists('feedback');
        Schema::dropIfExists('rendez_vous');
    }
};
