<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->unsignedTinyInteger('quality_score')->nullable()->after('notes');
            $table->string('quality_status')->default('pending')->after('quality_score');
            $table->string('client_final_status')->nullable()->after('quality_status'); // satisfied, problem_reported
            $table->timestamp('client_final_validated_at')->nullable()->after('client_final_status');
            $table->json('quality_summary')->nullable()->after('client_final_validated_at');

            $table->index(['quality_status', 'quality_score']);
            $table->index('client_final_status');
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropIndex(['quality_status', 'quality_score']);
            $table->dropIndex(['client_final_status']);
            $table->dropColumn([
                'quality_score',
                'quality_status',
                'client_final_status',
                'client_final_validated_at',
                'quality_summary',
            ]);
        });
    }
};