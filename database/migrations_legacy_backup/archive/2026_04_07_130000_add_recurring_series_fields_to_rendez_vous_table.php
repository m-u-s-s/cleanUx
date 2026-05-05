<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->uuid('recurring_series_id')->nullable()->after('recurrence_rule')->index();
            $table->string('recurrence_frequency', 20)->nullable()->after('recurring_series_id');
            $table->unsignedSmallInteger('recurrence_interval')->nullable()->after('recurrence_frequency');
            $table->date('recurrence_until')->nullable()->after('recurrence_interval');
            $table->unsignedSmallInteger('recurrence_count')->nullable()->after('recurrence_until');
            $table->json('recurrence_days')->nullable()->after('recurrence_count');
            $table->boolean('is_series_master')->default(false)->after('recurrence_days')->index();
            $table->unsignedSmallInteger('series_position')->nullable()->after('is_series_master');
            $table->string('series_status', 20)->nullable()->after('series_position')->index();
        });
    }

    public function down(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->dropIndex(['recurring_series_id']);
            $table->dropIndex(['is_series_master']);
            $table->dropIndex(['series_status']);
            $table->dropColumn([
                'recurring_series_id',
                'recurrence_frequency',
                'recurrence_interval',
                'recurrence_until',
                'recurrence_count',
                'recurrence_days',
                'is_series_master',
                'series_position',
                'series_status',
            ]);
        });
    }
};
