<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ACHEVER L'ALIGNEMENT DE `customer_credits`.
 *
 * La consolidation de juin 2026 a tranché : un crédit appartient à un client par `client_id`, et
 * s'impute sur une réservation par `rendez_vous_id`. Elle a retiré les colonnes « portefeuille »
 * (`balance`, `currency`) mais a laissé derrière elle `customer_user_id` et
 * `customer_organization_id` — deux colonnes qu'AUCUN code n'écrit, qu'aucun code ne lit, et qui
 * contiennent zéro valeur.
 *
 * POURQUOI LES RETIRER PLUTÔT QUE LES IGNORER : une colonne nommée `customer_user_id` à côté d'une
 * colonne `client_id`, sur une table d'argent, invite à se tromper. Le prochain développeur qui
 * cherche « le client d'un crédit » a une chance sur deux d'écrire dans celle que personne ne lit,
 * et son crédit deviendra invisible dans le portefeuille sans qu'aucune erreur ne le signale. Ce
 * dépôt a déjà payé ce prix ailleurs, avec deux colonnes vers `bookings` sur la table `missions`.
 *
 * Le retrait est sûr et vérifié : zéro ligne renseignée, zéro lecture, zéro écriture — ni dans
 * l'application, ni dans les fabriques, ni dans les seeders, ni dans les tests.
 */
return new class extends Migration
{
    private const ORPHELINES = ['customer_user_id', 'customer_organization_id'];

    public function up(): void
    {
        if (! Schema::hasTable('customer_credits')) {
            return;
        }

        foreach (self::ORPHELINES as $colonne) {
            if (! Schema::hasColumn('customer_credits', $colonne)) {
                continue;
            }

            /*
             * LA CONTRAINTE D'ABORD. MySQL refuse de retirer une colonne portée par une clé
             * étrangère (erreur 1828), et le nom de cette contrainte n'est pas devinable : il vient
             * de la convention de Laravel au moment où elle a été posée. On le LIT dans le schéma
             * plutôt que de le supposer — sous SQLite il n'y a rien à lire, la table est reconstruite.
             */
            foreach ($this->contraintesSur($colonne) as $contrainte) {
                Schema::table('customer_credits', function (Blueprint $table) use ($contrainte) {
                    $table->dropForeign($contrainte);
                });
            }

            /*
             * PUIS L'INDEX. Il survit à la contrainte et bloque à son tour : SQLite refuse de
             * laisser un index pendant après reconstruction de la table. L'ordre est donc imposé —
             * clé étrangère, index, colonne — et chaque étape a été trouvée en butant sur la
             * suivante.
             */
            if ($this->aUnIndex($colonne)) {
                Schema::table('customer_credits', function (Blueprint $table) use ($colonne) {
                    $table->dropIndex([$colonne]);
                });
            }

            Schema::table('customer_credits', function (Blueprint $table) use ($colonne) {
                $table->dropColumn($colonne);
            });
        }
    }

    /**
     * Ce qu'il faut passer à `dropForeign()` pour cette colonne — ou rien s'il n'y a pas de
     * contrainte. LES DEUX PILOTES SONT INTERROGÉS, et c'est indispensable : SQLite porte la même
     * contrainte que MySQL, mais l'annonce autrement. Ne regarder que `information_schema` faisait
     * passer la migration en production et échouer la suite de tests avec « unknown column in
     * foreign key definition » — SQLite reconstruit la table et refuse de laisser une clé
     * étrangère pendante.
     *
     * @return array<int, string|array<int, string>>
     */
    private function contraintesSur(string $colonne): array
    {
        $pilote = DB::connection()->getDriverName();

        if ($pilote === 'mysql') {
            $lignes = DB::select(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE '.
                'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? '.
                'AND REFERENCED_TABLE_NAME IS NOT NULL',
                [DB::connection()->getDatabaseName(), 'customer_credits', $colonne],
            );

            return array_map(fn ($ligne) => $ligne->CONSTRAINT_NAME, $lignes);
        }

        if ($pilote === 'sqlite') {
            // SQLite ne nomme pas ses contraintes : on les désigne par leur colonne, ce que
            // `dropForeign()` accepte sous forme de tableau.
            $liens = DB::select('PRAGMA foreign_key_list(customer_credits)');

            foreach ($liens as $lien) {
                if (($lien->from ?? null) === $colonne) {
                    return [[$colonne]];
                }
            }
        }

        return [];
    }

    private function aUnIndex(string $colonne): bool
    {
        $pilote = DB::connection()->getDriverName();
        $attendu = 'customer_credits_'.$colonne.'_index';

        if ($pilote === 'mysql') {
            foreach (DB::select('SHOW INDEX FROM customer_credits') as $index) {
                if (($index->Key_name ?? null) === $attendu) {
                    return true;
                }
            }

            return false;
        }

        if ($pilote === 'sqlite') {
            foreach (DB::select('PRAGMA index_list(customer_credits)') as $index) {
                if (($index->name ?? null) === $attendu) {
                    return true;
                }
            }
        }

        return false;
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_credits')) {
            return;
        }

        Schema::table('customer_credits', function (Blueprint $table) {
            foreach (self::ORPHELINES as $colonne) {
                if (! Schema::hasColumn('customer_credits', $colonne)) {
                    $table->unsignedBigInteger($colonne)->nullable()->index();
                }
            }
        });
    }
};
