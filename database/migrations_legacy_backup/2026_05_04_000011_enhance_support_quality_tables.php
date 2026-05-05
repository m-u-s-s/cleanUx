<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('incident_reports', 'attachments')) {
                $table->json('attachments')->nullable()->after('photos');
            }
            if (! Schema::hasColumn('incident_reports', 'first_response_at')) {
                $table->timestamp('first_response_at')->nullable()->after('meta');
            }
            if (! Schema::hasColumn('incident_reports', 'due_at')) {
                $table->timestamp('due_at')->nullable()->after('first_response_at');
            }
            if (! Schema::hasColumn('incident_reports', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('resolved_at');
            }
            if (! Schema::hasColumn('incident_reports', 'sla_policy')) {
                $table->string('sla_policy')->nullable()->after('priority');
            }
            if (! Schema::hasColumn('incident_reports', 'severity')) {
                $table->string('severity')->nullable()->after('sla_policy');
            }
        });

        Schema::table('complaint_cases', function (Blueprint $table) {
            if (! Schema::hasColumn('complaint_cases', 'first_response_at')) {
                $table->timestamp('first_response_at')->nullable()->after('admin_response');
            }
            if (! Schema::hasColumn('complaint_cases', 'due_at')) {
                $table->timestamp('due_at')->nullable()->after('first_response_at');
            }
            if (! Schema::hasColumn('complaint_cases', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('resolved_at');
            }
            if (! Schema::hasColumn('complaint_cases', 'sla_policy')) {
                $table->string('sla_policy')->nullable()->after('priority');
            }
            if (! Schema::hasColumn('complaint_cases', 'resolution_category')) {
                $table->string('resolution_category')->nullable()->after('sla_policy');
            }
        });

        Schema::table('quality_audits', function (Blueprint $table) {
            if (! Schema::hasColumn('quality_audits', 'attachment_evidence')) {
                $table->json('attachment_evidence')->nullable()->after('checklist');
            }
            if (! Schema::hasColumn('quality_audits', 'action_plan')) {
                $table->text('action_plan')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('quality_audits', 'follow_up_due_at')) {
                $table->timestamp('follow_up_due_at')->nullable()->after('follow_up_required');
            }
            if (! Schema::hasColumn('quality_audits', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('audited_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quality_audits', function (Blueprint $table) {
            foreach (['attachment_evidence', 'action_plan', 'follow_up_due_at', 'closed_at'] as $column) {
                if (Schema::hasColumn('quality_audits', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('complaint_cases', function (Blueprint $table) {
            foreach (['first_response_at', 'due_at', 'closed_at', 'sla_policy', 'resolution_category'] as $column) {
                if (Schema::hasColumn('complaint_cases', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('incident_reports', function (Blueprint $table) {
            foreach (['attachments', 'first_response_at', 'due_at', 'closed_at', 'sla_policy', 'severity'] as $column) {
                if (Schema::hasColumn('incident_reports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
