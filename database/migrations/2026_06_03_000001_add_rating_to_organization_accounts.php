<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_accounts', 'rating_avg')) {
                $table->decimal('rating_avg', 3, 2)->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('organization_accounts', 'rating_count')) {
                $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organization_accounts', function (Blueprint $table) {
            foreach (['rating_avg', 'rating_count'] as $c) {
                if (Schema::hasColumn('organization_accounts', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
