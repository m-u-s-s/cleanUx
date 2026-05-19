<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 32)->nullable()->after('platform_role');
            }

            if (! Schema::hasColumn('users', 'referred_by_referral_id')) {
                $table->unsignedBigInteger('referred_by_referral_id')->nullable()
                    ->after('referral_code');
            }
        });

        if (! $this->indexExists('users', 'users_referral_code_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('referral_code', 'users_referral_code_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'users_referral_code_unique')) {
                $table->dropUnique('users_referral_code_unique');
            }
            if (Schema::hasColumn('users', 'referred_by_referral_id')) {
                $table->dropColumn('referred_by_referral_id');
            }
            if (Schema::hasColumn('users', 'referral_code')) {
                $table->dropColumn('referral_code');
            }
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        $conn = Schema::getConnection();
        $driver = $conn->getDriverName();

        try {
            if ($driver === 'mysql' || $driver === 'mariadb') {
                $rows = $conn->select(
                    "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
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
                    "SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                    [$table, $name]
                );
                return count($rows) > 0;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }
};
