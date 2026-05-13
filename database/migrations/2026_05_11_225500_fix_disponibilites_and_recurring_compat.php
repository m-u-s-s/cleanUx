<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('disponibilites')) {
            Schema::create('disponibilites', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('user_id')->nullable();
                $table->date('date');
                $table->time('heure_debut');
                $table->time('heure_fin');

                $table->string('status')->default('available');
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(['user_id', 'date']);
                $table->index('status');
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                if (! Schema::hasColumn('bookings', 'recurring_series_id')) {
                    $table->unsignedBigInteger('recurring_series_id')->nullable()->index();
                }

                if (! Schema::hasColumn('bookings', 'assigned_provider_user_id')) {
                    $table->unsignedBigInteger('assigned_provider_user_id')->nullable()->index();
                }

                if (! Schema::hasColumn('bookings', 'assigned_employee_id')) {
                    $table->unsignedBigInteger('assigned_employee_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilites');
    }
};
