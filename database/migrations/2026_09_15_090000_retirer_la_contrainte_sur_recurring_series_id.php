<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RETIRER LA CONTRAINTE POSÉE SUR `bookings.recurring_series_id`.
 *
 * ── POURQUOI ELLE NE PEUT PAS EXISTER ────────────────────────────────────────────────────────
 *
 * La migration `2026_09_13` l'a posée vers `bookings`, sur la foi du modèle qui déclare
 * `belongsTo(Booking::class, 'recurring_series_id')`. La suite complète l'a rejetée, et en
 * cherchant pourquoi on découvre que cette colonne reçoit TROIS choses différentes :
 *
 *   `CreateRecurringSeriesAction:54`   y écrit un UUID — `(string) Str::uuid()`
 *   `ProcessRecurringBookings:143`     y écrit l'identifiant d'une `recurring_booking_series`
 *   le modèle `Booking`                la déclare comme un identifiant de RÉSERVATION
 *
 * Trois notions dans une seule colonne, et la colonne est un `bigint unsigned` : le UUID n'y entre
 * même pas. Aucune clé étrangère ne peut être juste ici — la contraindre vers l'une des trois
 * cibles casserait les deux autres chemins.
 *
 * ── CE QU'ON NE FAIT PAS, ET POURQUOI ────────────────────────────────────────────────────────
 *
 * On ne tranche pas la question. Unifier ces trois usages change le comportement des séries
 * récurrentes — quelle valeur fait foi, comment migrer l'existant, ce que devient
 * `recurring_booking_series_id` qui vit à côté et porte, lui, le bon type et le bon sens. C'est une
 * décision de conception, pas une correction de schéma, et elle se prend en connaissance des
 * données réelles.
 *
 * Cette migration se contente donc de défaire ce qui a été posé à tort. L'ambiguïté, elle, est
 * signalée : c'est le défaut le plus sérieux que ce chantier ait mis au jour, et il existait avant
 * lui — la contrainte n'a fait que le rendre visible.
 *
 * L'index simple posé sur la colonne par `2026_09_10` est CONSERVÉ : indexer une colonne
 * ambiguë reste utile, puisque les trois chemins la filtrent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'recurring_series_id')) {
            return;
        }

        foreach ($this->contraintesSurLaColonne() as $nom) {
            Schema::table('bookings', fn (Blueprint $t) => $t->dropForeign($nom));
        }
    }

    public function down(): void
    {
        // Volontairement vide : reposer une contrainte dont on vient d'établir qu'elle ne peut pas
        // être juste reviendrait à recasser la création de séries récurrentes.
    }

    /**
     * Les contraintes que porte la colonne, quel que soit leur nom.
     *
     * On demande au schéma plutôt que de supposer un nom : la contrainte a été posée par une autre
     * migration, sous un nom qu'elle a choisi.
     *
     * @return list<string>
     */
    private function contraintesSurLaColonne(): array
    {
        if (DB::getDriverName() !== 'mysql') {
            // SQLite reconstruit la table à chaque migration : la suite repart d'un schéma neuf et
            // il n'y a rien à défaire.
            return [];
        }

        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'bookings')
            ->where('COLUMN_NAME', 'recurring_series_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME')
            ->all();
    }
};
