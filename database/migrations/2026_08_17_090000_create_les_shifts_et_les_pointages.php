<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LES PLANNINGS D'ÉQUIPE (E19) ET LES FEUILLES D'HEURES (E20). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shifts')) {
            Schema::create('shifts', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('organization_account_id');
                $table->unsignedBigInteger('provider_agency_id')->nullable();
                $table->unsignedBigInteger('field_team_id')->nullable();
                $table->unsignedBigInteger('user_id');

                $table->timestamp('starts_at');
                $table->timestamp('ends_at');

                // `planned`, `published`, `cancelled`. Un planning en préparation ne doit pas
                // rendre quelqu'un assignable : on publie quand c'est arrêté.
                $table->string('status', 20)->default('planned');

                // La récurrence est décrite, pas dépliée en mille lignes : « tous les lundis » se
                // range dans une règle, et les exceptions se posent en shifts ordinaires.
                $table->string('recurrence_rule')->nullable();

                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                // Nom court : MySQL refuse au-delà de 64 caractères.
                $table->index(['user_id', 'starts_at'], 'shifts_user_start_idx');
                $table->index(['organization_account_id', 'starts_at'], 'shifts_org_start_idx');

                $table->foreign('organization_account_id')->references('id')->on('organization_accounts')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('time_entries')) {
            Schema::create('time_entries', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('organization_account_id');
                $table->unsignedBigInteger('user_id');
                // La mission pointée, quand il y en a une. Un déplacement inter-sites ou une
                // réunion d'équipe se pointent aussi, et n'ont pas de mission.
                $table->unsignedBigInteger('mission_id')->nullable();
                $table->unsignedBigInteger('shift_id')->nullable();

                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();

                // LES MINUTES SONT STOCKÉES, PAS RECALCULÉES À CHAQUE LECTURE.
                $table->unsignedInteger('worked_minutes')->default(0);
                $table->unsignedInteger('paused_minutes')->default(0);

                // `auto` (géo-barrière du suivi) ou `manual` (saisie). La distinction décide de
                // l'approbation.
                $table->string('source', 12)->default('auto');
                $table->string('status', 20)->default('recorded');

                $table->unsignedBigInteger('approved_by_user_id')->nullable();
                $table->timestamp('approved_at')->nullable();

                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'started_at'], 'time_entries_user_start_idx');
                $table->index(['organization_account_id', 'started_at'], 'time_entries_org_start_idx');

                $table->foreign('organization_account_id')->references('id')->on('organization_accounts')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('mission_id')->references('id')->on('missions')->nullOnDelete();
                $table->foreign('shift_id')->references('id')->on('shifts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('shifts');
    }
};
