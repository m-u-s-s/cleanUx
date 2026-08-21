<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INDEXER LES COLONNES DE JOINTURE DU CŒUR CHAUD.
 *
 * ── CE QU'ON A MESURÉ ────────────────────────────────────────────────────────────────────────
 *
 * 822 colonnes `*_id` existent dans ce schéma. 170 d'entre elles ne sont EN TÊTE d'aucun index —
 * et elles se concentrent précisément sur les tables les plus reliées du dépôt : `missions` (7),
 * `bookings` (6), `users` (6), `organization_accounts` (5), `service_zones` (4). Ce sont les
 * mêmes que le graphe d'appels désigne comme nœuds centraux (User 1279 liens, Booking 873,
 * Mission 538).
 *
 * Sans index de tête, chaque jointure et chaque filtre sur ces colonnes est un BALAYAGE COMPLET.
 * Tant que la base tient dans quelques milliers de lignes, personne ne le voit. À l'échelle visée
 * — plusieurs pays, tous les métiers, un grand nombre de missions simultanées — c'est la première
 * chose qui s'effondre, et elle s'effondre sur les tables que TOUT le reste interroge.
 *
 * ── POURQUOI `SEQ_IN_INDEX = 1` ──────────────────────────────────────────────────────────────
 *
 * Une colonne placée en SECONDE position d'un index composite n'est pas utilisable seule : MySQL
 * ne peut attaquer un index que par son préfixe gauche. Compter ces colonnes comme « indexées »
 * serait se mentir, et c'est pourquoi l'audit ne retient que la tête.
 *
 * ── LES DEUX NATURES DE COLONNES ─────────────────────────────────────────────────────────────
 *
 * `bigint`  : de vraies clés étrangères, empruntées par les jointures.
 * `varchar` : des identifiants EXTERNES (Stripe, KYC). Ils comptent au moins autant : à chaque
 *             webhook reçu, l'application cherche `users.stripe_id = 'cus_…'`. Sans index, chaque
 *             événement Stripe balaie la table des comptes. Vérifié dans le code avant d'indexer —
 *             `stripe_id` est interrogé à neuf endroits, `stripe_connect_account_id` à quatre.
 *
 * ── CE QUE CETTE MIGRATION NE FAIT PAS ───────────────────────────────────────────────────────
 *
 * Elle n'ajoute AUCUNE contrainte, aucune unicité, ne renomme ni ne supprime rien. Un index est
 * additif : il ne peut pas changer le résultat d'une requête, seulement le temps qu'elle met. Les
 * clés étrangères manquantes (347) et l'unicité de `stripe_id` sont des décisions séparées, qui
 * peuvent refuser des données existantes — elles ne se glissent pas dans une migration d'index.
 *
 * ── NOMS D'INDEX ─────────────────────────────────────────────────────────────────────────────
 *
 * Nommés à la main et courts. MySQL refuse un identifiant de plus de 64 caractères, et le plus
 * long de ce schéma en fait déjà EXACTEMENT 64 : les noms engendrés automatiquement
 * (`table_colonne_index`) sont à un cheveu de casser la migration — et SQLite, sur lequel tourne
 * la suite, ne dirait rien.
 */
return new class extends Migration
{
    /**
     * Table => [colonne => nom d'index court].
     *
     * @var array<string, array<string, string>>
     */
    private array $index = [
        'missions' => [
            'organization_account_id' => 'ix_missions_org_account',
            'organization_site_id' => 'ix_missions_org_site',
            'service_catalog_id' => 'ix_missions_service_catalog',
            'service_zone_id' => 'ix_missions_service_zone',
            'lead_employee_id' => 'ix_missions_lead_employee',
            'started_by_user_id' => 'ix_missions_started_by',
            'closed_by_user_id' => 'ix_missions_closed_by',
        ],
        'bookings' => [
            'organization_account_id' => 'ix_bookings_org_account',
            'postal_code_id' => 'ix_bookings_postal_code',
            'client_place_id' => 'ix_bookings_client_place',
            'dropoff_place_id' => 'ix_bookings_dropoff_place',
            'deposit_payment_intent_id' => 'ix_bookings_deposit_intent',
            'stripe_transfer_id' => 'ix_bookings_stripe_transfer',
        ],
        'users' => [
            'organization_account_id' => 'ix_users_org_account',
            'postal_code_id' => 'ix_users_postal_code',
            'primary_service_zone_id' => 'ix_users_primary_zone',
            'referred_by_referral_id' => 'ix_users_referred_by',
            'stripe_id' => 'ix_users_stripe',
            'stripe_connect_account_id' => 'ix_users_stripe_connect',
        ],
        'organization_accounts' => [
            'country_id' => 'ix_org_accounts_country',
            'region_id' => 'ix_org_accounts_region',
            'province_id' => 'ix_org_accounts_province',
            'commune_id' => 'ix_org_accounts_commune',
            'postal_code_id' => 'ix_org_accounts_postal_code',
        ],
        'service_zones' => [
            'parent_zone_id' => 'ix_service_zones_parent',
            'region_id' => 'ix_service_zones_region',
            'province_id' => 'ix_service_zones_province',
            'commune_id' => 'ix_service_zones_commune',
        ],
        'provider_profiles' => [
            'kyc_last_verification_id' => 'ix_provider_kyc_last_verif',
            'kyc_external_applicant_id' => 'ix_provider_kyc_applicant',
            'stripe_connect_account_id' => 'ix_provider_stripe_connect',
        ],
    ];

    public function up(): void
    {
        foreach ($this->index as $table => $colonnes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($colonnes as $colonne => $nom) {
                if (! Schema::hasColumn($table, $colonne)) {
                    continue;
                }

                if ($this->dejaEnTeteDUnIndex($table, $colonne)) {
                    continue;
                }

                Schema::table($table, fn (Blueprint $t) => $t->index($colonne, $nom));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->index as $table => $colonnes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($colonnes as $nom) {
                if ($this->indexExiste($table, $nom)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($nom));
                }
            }
        }
    }

    /**
     * La colonne est-elle DÉJÀ en tête d'un index ?
     *
     * On interroge le schéma plutôt que de supposer : une colonne peut avoir gagné son index par
     * une autre migration, ou porter déjà une clé étrangère — qui, sous MySQL, crée son index.
     * Poser un doublon ne casserait rien à la lecture mais coûterait à chaque écriture.
     *
     * SQLite (la suite de tests) n'a pas `information_schema` : on y retombe sur un contrôle qui
     * laisse simplement passer, la création d'index y étant de toute façon sans risque.
     */
    private function dejaEnTeteDUnIndex(string $table, string $colonne): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $colonne)
            ->where('SEQ_IN_INDEX', 1)
            ->exists();
    }

    private function indexExiste(string $table, string $nom): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return true;
        }

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $nom)
            ->exists();
    }
};
