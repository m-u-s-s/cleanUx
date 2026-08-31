<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Les cles avec lesquelles CE passage en a fini : le drain purge sur leur intersection. */
    public function up(): void
    {
        Schema::table('automation_runs', function (Blueprint $table) {
            $table->json('entites_finies')->nullable()->after('entites_eligibles');
        });
    }

    public function down(): void
    {
        Schema::table('automation_runs', function (Blueprint $table) {
            $table->dropColumn('entites_finies');
        });
    }
};
