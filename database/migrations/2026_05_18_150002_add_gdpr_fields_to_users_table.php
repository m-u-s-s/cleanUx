<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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
        } catch (\Throwable $e) {
            // index existant
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            try { $table->dropIndex('users_anonymized_at_index'); } catch (\Throwable $e) {}
            try { $table->dropIndex('users_deletion_scheduled_at_index'); } catch (\Throwable $e) {}

            foreach ([
                'anonymized_at', 'processing_restricted_at',
                'deletion_scheduled_at', 'last_gdpr_action_at',
            ] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
