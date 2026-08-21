<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `users.account_type` CONTREDISAIT `users.role`, SUR LA MÊME LIGNE.
 *
 * ── CE QU'ON A MESURÉ ────────────────────────────────────────────────────────────────────────
 *
 * La colonne est `varchar(255) NOT NULL DEFAULT 'client_personal'`. Elle valait donc
 * `client_personal` pour les TRENTE comptes de `brio`, dont onze employés et quatre
 * administrateurs. Onze lignes portaient exactement `role = 'employe'` ET
 * `account_type = 'client_personal'`.
 *
 * ── D'OÙ VIENT LA CONTRADICTION ──────────────────────────────────────────────────────────────
 *
 * Deux chemins d'inscription, un seul renseigne la colonne :
 *
 *   `CreateNewUser.php:121` (web)         la pose depuis le champ du formulaire — correct.
 *   `ApiAuthController.php:261` (API)     ne la pose PAS. Elle lit pourtant le même champ pour en
 *                                         déduire `role` et `platform_role` par `forceFill`, puis
 *                                         laisse le défaut SQL s'appliquer à `account_type`.
 *
 * Une inscription de prestataire par l'API produisait donc, dans une seule transaction, un compte
 * avec `role = 'employe'` et `account_type = 'client_personal'`.
 *
 * ── POURQUOI ON LA CORRIGE PLUTÔT QUE DE LA SUPPRIMER ────────────────────────────────────────
 *
 * Aucun code ne lit `$user->account_type` aujourd'hui — les 47 occurrences des tests et les
 * lectures de `CreateNewUser` et `ApiAuthController` visent toutes la CHARGE DE LA REQUÊTE, pas la
 * colonne. Elle ne nuit donc à personne pour l'instant. Mais elle est chargée : le premier
 * `where('account_type', 'provider_company')` rendra zéro ligne, et quiconque lui fera confiance
 * traitera onze prestataires en clients particuliers. Une colonne qui se trompe plus souvent
 * qu'elle n'a raison, tout en ayant l'air de faire autorité, est pire qu'une colonne absente.
 *
 * On ne la supprime pas parce qu'elle porte l'INTENTION d'inscription — c'est elle qui décide du
 * rôle aux deux endroits ci-dessus, et c'est le seul endroit où ce choix reste consigné.
 *
 * ── LE RATTRAPAGE ────────────────────────────────────────────────────────────────────────────
 *
 * Il lit les champs TYPÉS, jamais le nom de la colonne : `provider_profiles.provider_type` et
 * `customer_profiles.customer_type` sont ce que la plateforme interroge réellement
 * (`HasUserTypeChecks`). Les comptes dont aucun profil ne tranche sont laissés tels quels — on ne
 * devine pas à la place d'une donnée absente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'account_type')) {
            return;
        }

        $this->corrigerDepuis('provider_profiles', 'provider_type', [
            'company_worker' => 'provider_company',
            'independent' => 'provider_independent',
            // `individual` et `independent` disent la même chose sous deux noms : la divergence est
            // signalée, elle ne se tranche pas ici.
            'individual' => 'provider_independent',
        ]);

        $this->corrigerDepuis('customer_profiles', 'customer_type', [
            'company' => 'client_company',
            'personal' => 'client_personal',
        ]);
    }

    public function down(): void
    {
        // Volontairement vide : on ne sait plus quelles lignes portaient le défaut, et les remettre
        // à `client_personal` reviendrait à recréer sciemment la contradiction.
    }

    /**
     * @param  array<string, string>  $correspondance
     */
    private function corrigerDepuis(string $table, string $colonne, array $correspondance): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $colonne)) {
            return;
        }

        foreach ($correspondance as $typeProfil => $typeCompte) {
            $ids = DB::table($table)->where($colonne, $typeProfil)->pluck('user_id');

            if ($ids->isEmpty()) {
                continue;
            }

            DB::table('users')
                ->whereIn('id', $ids)
                ->where('account_type', '<>', $typeCompte)
                ->update(['account_type' => $typeCompte]);
        }
    }
};
