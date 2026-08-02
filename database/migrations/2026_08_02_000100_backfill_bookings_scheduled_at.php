<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstitue `scheduled_at` sur les réservations existantes.
 *
 * La colonne n'était remplie par aucun chemin : absente de `$fillable`, toute écriture était
 * silencieusement ignorée. Le moteur d'annulation la lit pourtant EN PREMIER et retombait sur
 * `date`, de type DATE sur MySQL, donc tronquée au jour. Les frais d'annulation se calculaient
 * ainsi contre minuit au lieu de l'heure réelle du rendez-vous — un client annulant un
 * rendez-vous de 17 h trente heures à l'avance était facturé au palier « moins de 24 h ».
 *
 * Le code corrige les écritures FUTURES ; cette migration corrige le PASSÉ. Sans elle, toutes les
 * réservations déjà en base gardent leur `scheduled_at` nul et continuent d'être annulées au
 * mauvais tarif.
 *
 * On ne touche qu'aux lignes où la valeur manque et où le jour ET l'heure sont connus : rien
 * d'inventé, rien d'écrasé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'scheduled_at')) {
            return;
        }

        DB::table('bookings')
            ->whereNull('scheduled_at')
            ->whereNotNull('date')
            ->whereNotNull('heure')
            ->orderBy('id')
            // Par lots : la table peut être volumineuse, et un UPDATE global la verrouillerait
            // le temps du traitement.
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $timestamp = strtotime(substr((string) $row->date, 0, 10).' '.substr((string) $row->heure, 0, 8));

                    if ($timestamp === false) {
                        continue;
                    }

                    DB::table('bookings')
                        ->where('id', $row->id)
                        ->update(['scheduled_at' => date('Y-m-d H:i:s', $timestamp)]);
                }
            });
    }

    /**
     * Aucun retour en arrière.
     *
     * Remettre `scheduled_at` à nul ne restaurerait rien : la valeur était absente par défaut, pas
     * par choix. L'effacer reviendrait à réintroduire le défaut de facturation.
     */
    public function down(): void {}
};
