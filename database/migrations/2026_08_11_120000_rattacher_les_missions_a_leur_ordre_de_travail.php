<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RENDRE À `missions` LES TROIS LIENS QUE TOUT LE CODE SUPPOSAIT DÉJÀ.
 *
 * `EnterpriseWorkOrderMissionGeneratorService` écrit `enterprise_work_order_id`, `mission_batch_id`
 * et `mission_task_segment_id` à la création de chaque mission d'un chantier d'entreprise. Aucune
 * de ces trois colonnes n'existait. Eloquent ÉCARTE EN SILENCE ce qu'il ne peut pas assigner : les
 * trois valeurs partaient à la poubelle sans une erreur, sans un journal, sans un test rouge.
 *
 * CE QUI ÉTAIT PERDU : la traçabilité entière de l'exécution B2B. Une mission née d'un ordre de
 * travail ne savait plus de quel ordre elle venait, à quel lot elle appartenait, ni quel segment de
 * tâche elle exécutait. `Mission::missionTaskSegment()` déclare pourtant la relation, et le centre
 * d'opérations du chef d'équipe filtre par lot — sur une hiérarchie détournée, faute de mieux.
 *
 * L'OUBLI EST DATÉ. La série de migrations du 28 mai 2026 a posé `mission_task_segment_id` sur
 * `mission_member_statuses`, `mission_task_segment_assignments` et
 * `mission_reinforcement_requests` — mais pas sur `missions`, la table qui en avait le plus besoin.
 *
 * Les clés étrangères sont posées en `nullOnDelete` : une mission survit à la suppression de son
 * ordre de travail, elle perd seulement son rattachement. L'inverse effacerait des interventions
 * réellement effectuées.
 */
return new class extends Migration
{
    /** @var array<string, string> colonne => table référencée */
    private const LIENS = [
        'enterprise_work_order_id' => 'enterprise_work_orders',
        'mission_batch_id' => 'mission_batches',
        'mission_task_segment_id' => 'mission_task_segments',
    ];

    public function up(): void
    {
        foreach (self::LIENS as $colonne => $cible) {
            if (Schema::hasColumn('missions', $colonne) || ! Schema::hasTable($cible)) {
                continue;
            }

            Schema::table('missions', function (Blueprint $table) use ($colonne, $cible) {
                $table->unsignedBigInteger($colonne)->nullable()->after('booking_id');
                $table->index($colonne);
                $table->foreign($colonne)->references('id')->on($cible)->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::LIENS) as $colonne) {
            if (! Schema::hasColumn('missions', $colonne)) {
                continue;
            }

            Schema::table('missions', function (Blueprint $table) use ($colonne) {
                // L'ordre compte : la contrainte s'appuie sur l'index, et MySQL refuse de retirer
                // l'index tant que la clé étrangère existe.
                $table->dropForeign([$colonne]);
                $table->dropIndex([$colonne]);
                $table->dropColumn($colonne);
            });
        }
    }
};
