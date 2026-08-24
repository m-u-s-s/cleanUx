<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L4 — add referential-integrity constraints to type-FK columns that the "fix/roundN" migrations added as bare unsignedBigInteger (no ->constrained()).
 *
 * @see docs/AUDIT-QUARANTINE-BATCH.md (remaining ambiguous columns: channel_id, finance_*).
 */
return new class extends Migration
{
    /** @var array<int, array{table:string, column:string, references:string, onDelete:string}> */
    private array $fks = [
        ['table' => 'conversations', 'column' => 'mission_id', 'references' => 'missions', 'onDelete' => 'nullOnDelete'],
        ['table' => 'conversations', 'column' => 'created_by_user_id', 'references' => 'users', 'onDelete' => 'nullOnDelete'],
        ['table' => 'customer_credits', 'column' => 'rendez_vous_id', 'references' => 'bookings', 'onDelete' => 'nullOnDelete'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return; // SQLite can't add FKs to existing tables; prod (MySQL/Postgres) only.
        }

        foreach ($this->fks as $fk) {
            if (! Schema::hasTable($fk['table'])
                || ! Schema::hasColumn($fk['table'], $fk['column'])
                || ! Schema::hasTable($fk['references'])) {
                continue;
            }

            // Nullify orphans so the constraint can be created.
            DB::table($fk['table'])
                ->whereNotNull($fk['column'])
                ->whereNotIn($fk['column'], DB::table($fk['references'])->select('id'))
                ->update([$fk['column'] => null]);

            try {
                Schema::table($fk['table'], function (Blueprint $table) use ($fk) {
                    $table->foreign($fk['column'])
                        ->references('id')->on($fk['references'])
                        ->{$fk['onDelete']}();
                });
            } catch (Throwable $e) {
                // FK already exists or engine rejected it — skip without failing the migration.
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach ($this->fks as $fk) {
            if (! Schema::hasTable($fk['table']) || ! Schema::hasColumn($fk['table'], $fk['column'])) {
                continue;
            }
            try {
                Schema::table($fk['table'], function (Blueprint $table) use ($fk) {
                    $table->dropForeign([$fk['column']]);
                });
            } catch (Throwable $e) {
                // no-op
            }
        }
    }
};
