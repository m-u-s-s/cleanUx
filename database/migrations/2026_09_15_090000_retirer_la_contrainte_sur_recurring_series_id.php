<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** RETIRER LA CONTRAINTE POSÉE SUR `bookings.recurring_series_id`. */
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
