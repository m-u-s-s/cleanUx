<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES PLANNINGS D'ÉQUIPE (E19) ET LES FEUILLES D'HEURES (E20).
 *
 * E19 — « QUI TRAVAILLE QUAND » N'ÉTAIT ÉCRIT NULLE PART. `WorkerAvailabilityService` répond
 * aujourd'hui à « cette personne est-elle déjà prise à cette heure-là » en regardant ses missions —
 * et c'est tout ce qu'il peut faire, faute de planning. Conséquence : l'auto-assignation considère
 * comme disponible quelqu'un qui ne travaille pas ce jour-là, et lui envoie une course à
 * vingt-trois heures un dimanche.
 *
 * Les créneaux de disponibilité existants sont un concept d'INDÉPENDANT — celui qui publie ses
 * horaires sur la place de marché. Un salarié ne s'en déclare pas : c'est son employeur qui le
 * planifie. `shifts` est cette planification-là, et rien d'autre.
 *
 * E20 — LE POINTAGE EST LE PENDANT DU PLANNING. Le shift dit ce qui était prévu, `time_entries` dit
 * ce qui s'est réellement passé. Les confondre reviendrait à payer le prévu — ce qui arrange
 * l'employeur les jours de retard et le salarié les jours de dépassement, et fâche tout le monde le
 * reste du temps.
 *
 * L'ENTRÉE PORTE SA SOURCE : `auto` quand elle vient de la géo-barrière du suivi, `manual` quand
 * elle est saisie. Et une saisie manuelle demande une APPROBATION — sans quoi le pointage
 * deviendrait déclaratif, ce que ni la paie ni le client ne peuvent accepter.
 */
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

                /*
                 * LES MINUTES SONT STOCKÉES, PAS RECALCULÉES À CHAQUE LECTURE.
                 *
                 * Une feuille d'heures se relit des mois plus tard, après que le fuseau, la
                 * mission ou les pauses ont changé. Recalculer donnerait une valeur différente de
                 * celle qui a été payée.
                 */
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
