<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Compat subscriptions.user_id
        |--------------------------------------------------------------------------
        | Ton code/dashboard cherche subscriptions.user_id, mais Cashier utilise
        | billable_type + billable_id. On ajoute donc user_id pour compatibilité.
        */
        if (Schema::hasTable('subscriptions') && ! Schema::hasColumn('subscriptions', 'user_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Ancienne table limites_journalieres
        |--------------------------------------------------------------------------
        | Certains composants emploient encore limites_journalieres.
        */
        if (! Schema::hasTable('limites_journalieres')) {
            Schema::create('limites_journalieres', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('employe_id')->nullable()->index();

                $table->date('date')->index();

                $table->unsignedInteger('limite')->default(5);
                $table->unsignedInteger('max_rendez_vous')->default(5);
                $table->unsignedInteger('max_bookings')->default(5);
                $table->unsignedInteger('max_minutes')->nullable();

                $table->boolean('locked_by_admin')->default(false);
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->unique(['user_id', 'date'], 'limites_journalieres_user_date_unique');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Copier les limites modernes vers l’ancienne table si possible
        |--------------------------------------------------------------------------
        */
        if (
            Schema::hasTable('provider_daily_limits')
            && Schema::hasTable('limites_journalieres')
            && DB::table('limites_journalieres')->count() === 0
        ) {
            DB::table('provider_daily_limits')
                ->orderBy('id')
                ->chunk(100, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('limites_journalieres')->insert([
                            'user_id' => $row->provider_user_id ?? null,
                            'employe_id' => $row->provider_user_id ?? null,
                            'date' => $row->date,
                            'limite' => $row->max_bookings ?? 5,
                            'max_rendez_vous' => $row->max_bookings ?? 5,
                            'max_bookings' => $row->max_bookings ?? 5,
                            'max_minutes' => $row->max_minutes ?? null,
                            'locked_by_admin' => $row->locked_by_admin ?? false,
                            'notes' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('limites_journalieres');
    }
};
