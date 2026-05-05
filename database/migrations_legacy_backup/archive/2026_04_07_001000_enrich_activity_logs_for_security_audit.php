<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_logs', 'domain')) {
                $table->string('domain')->nullable()->after('action');
            }

            if (! Schema::hasColumn('activity_logs', 'severity')) {
                $table->string('severity')->default('info')->after('domain');
            }

            if (! Schema::hasColumn('activity_logs', 'is_critical')) {
                $table->boolean('is_critical')->default(false)->after('severity');
            }

            if (! Schema::hasColumn('activity_logs', 'route_name')) {
                $table->string('route_name')->nullable()->after('target_id');
            }

            if (! Schema::hasColumn('activity_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('route_name');
            }

            if (! Schema::hasColumn('activity_logs', 'user_agent')) {
                $table->string('user_agent', 500)->nullable()->after('ip_address');
            }

            if (! Schema::hasColumn('activity_logs', 'request_id')) {
                $table->string('request_id', 100)->nullable()->after('user_agent');
            }

            if (! Schema::hasColumn('activity_logs', 'service_zone_id')) {
                $table->foreignId('service_zone_id')
                    ->nullable()
                    ->after('request_id')
                    ->constrained('service_zones')
                    ->nullOnDelete();
            }
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('domain', 'activity_logs_domain_index');
            $table->index('severity', 'activity_logs_severity_index');
            $table->index('is_critical', 'activity_logs_is_critical_index');
            $table->index('request_id', 'activity_logs_request_id_index');
            $table->index('service_zone_id', 'activity_logs_service_zone_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logs', 'service_zone_id')) {
                $table->dropConstrainedForeignId('service_zone_id');
            }

            foreach ([
                'activity_logs_domain_index',
                'activity_logs_severity_index',
                'activity_logs_is_critical_index',
                'activity_logs_request_id_index',
                'activity_logs_service_zone_id_index',
            ] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (Throwable $e) {
                    // ignore missing index during rollback safety
                }
            }

            foreach (['request_id', 'user_agent', 'ip_address', 'route_name', 'is_critical', 'severity', 'domain'] as $column) {
                if (Schema::hasColumn('activity_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
