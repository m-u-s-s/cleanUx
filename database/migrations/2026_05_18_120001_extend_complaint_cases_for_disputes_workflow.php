<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étend complaint_cases avec les colonnes du workflow Disputes v2.
 * Le model ComplaintCase déclare déjà ces colonnes en fillable, mais la
 * table actuelle ne les contient pas (dette latente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaint_cases', function (Blueprint $table) {
            $columns = [
                'rendez_vous_id' => fn ($t) => $t->unsignedBigInteger('rendez_vous_id')->nullable(),
                'booking_id' => fn ($t) => $t->unsignedBigInteger('booking_id')->nullable(),
                'organization_account_id' => fn ($t) => $t->unsignedBigInteger('organization_account_id')->nullable(),
                'provider_user_id' => fn ($t) => $t->unsignedBigInteger('provider_user_id')->nullable(),
                'assigned_to' => fn ($t) => $t->unsignedBigInteger('assigned_to')->nullable(),

                'severity' => fn ($t) => $t->string('severity', 16)->default('medium'),
                'reference' => fn ($t) => $t->string('reference', 32)->nullable(),
                'resolution_category' => fn ($t) => $t->string('resolution_category', 32)->nullable(),
                'admin_response' => fn ($t) => $t->text('admin_response')->nullable(),

                'escalation_level' => fn ($t) => $t->unsignedTinyInteger('escalation_level')->default(0),
                'escalated_at' => fn ($t) => $t->timestamp('escalated_at')->nullable(),
                'auto_resolved' => fn ($t) => $t->boolean('auto_resolved')->default(false),

                'first_response_at' => fn ($t) => $t->timestamp('first_response_at')->nullable(),
                'resolved_at' => fn ($t) => $t->timestamp('resolved_at')->nullable(),
                'closed_at' => fn ($t) => $t->timestamp('closed_at')->nullable(),
                'last_activity_at' => fn ($t) => $t->timestamp('last_activity_at')->nullable(),
            ];

            foreach ($columns as $name => $builder) {
                if (! Schema::hasColumn('complaint_cases', $name)) {
                    $builder($table);
                }
            }
        });

        try {
            Schema::table('complaint_cases', function (Blueprint $table) {
                $table->index(['status', 'due_at'], 'complaint_cases_status_due_at_index');
                $table->index('provider_user_id', 'complaint_cases_provider_user_id_index');
                $table->index('assigned_to', 'complaint_cases_assigned_to_index');
                $table->unique('reference', 'complaint_cases_reference_unique');
            });
        } catch (\Throwable $e) {
            // index/unique déjà présents — ignore
        }
    }

    public function down(): void
    {
        Schema::table('complaint_cases', function (Blueprint $table) {
            foreach ([
                'complaint_cases_status_due_at_index',
                'complaint_cases_provider_user_id_index',
                'complaint_cases_assigned_to_index',
            ] as $idx) {
                try { $table->dropIndex($idx); } catch (\Throwable $e) {}
            }
            try { $table->dropUnique('complaint_cases_reference_unique'); } catch (\Throwable $e) {}

            foreach ([
                'rendez_vous_id', 'booking_id', 'organization_account_id',
                'provider_user_id', 'assigned_to', 'severity', 'reference',
                'resolution_category', 'admin_response', 'escalation_level',
                'escalated_at', 'auto_resolved', 'first_response_at',
                'resolved_at', 'closed_at', 'last_activity_at',
            ] as $col) {
                if (Schema::hasColumn('complaint_cases', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
