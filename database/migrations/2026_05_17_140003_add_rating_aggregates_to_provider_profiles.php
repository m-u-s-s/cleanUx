<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('provider_profiles', 'rating_avg')) {
                $table->decimal('rating_avg', 3, 2)->nullable()->after('bio');
            }
            if (! Schema::hasColumn('provider_profiles', 'rating_count')) {
                $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
            }
            if (! Schema::hasColumn('provider_profiles', 'rating_distribution')) {
                $table->json('rating_distribution')->nullable()->after('rating_count');
            }
            if (! Schema::hasColumn('provider_profiles', 'rating_dimensions')) {
                $table->json('rating_dimensions')->nullable()->after('rating_distribution');
            }
            if (! Schema::hasColumn('provider_profiles', 'rating_last_at')) {
                $table->timestamp('rating_last_at')->nullable()->after('rating_dimensions');
            }
        });

        Schema::table('provider_profiles', function (Blueprint $table) {
            if (! $this->indexExists('provider_profiles', 'provider_profiles_rating_avg_index')) {
                $table->index('rating_avg', 'provider_profiles_rating_avg_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            if ($this->indexExists('provider_profiles', 'provider_profiles_rating_avg_index')) {
                $table->dropIndex('provider_profiles_rating_avg_index');
            }

            foreach ([
                'rating_avg',
                'rating_count',
                'rating_distribution',
                'rating_dimensions',
                'rating_last_at',
            ] as $col) {
                if (Schema::hasColumn('provider_profiles', $col)) {
                    $table->dropColumn($col);
                }
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
