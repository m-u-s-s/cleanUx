<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CINQ CLÉS ÉTRANGÈRES QUI POINTAIENT VERS LA MAUVAISE TABLE.
 *
 * ── CE QUI S'EST PASSÉ ───────────────────────────────────────────────────────────────────────
 *
 * La migration `2026_09_12` a déduit la table parente du NOM de la colonne. Elle traitait tout
 * préfixe d'action (`assigned_`, `created_by_`, `approved_by_`…) comme désignant une personne, donc
 * `users`. La règle est juste pour `assigned_by_user_id` ; elle est FAUSSE dès que ce qu'on assigne
 * n'est pas une personne :
 *
 *   `assigned_provider_organization_id` — une organisation, pas un utilisateur
 *   `assigned_field_team_id`            — une équipe
 *   `assigned_service_partner_id`       — un partenaire
 *   `lead_assignment_id`                — une affectation, et le préfixe `lead_` a fait le reste
 *   `invoice_id` (cycles d'abonnement)  — la facture d'ABONNEMENT, pas la facture générale
 *
 * ── COMMENT ON L'A SU ────────────────────────────────────────────────────────────────────────
 *
 * La suite complète : 49 tests en échec, tous sur « FOREIGN KEY constraint failed ». La fabrique
 * de réservation renseigne `assigned_provider_organization_id` avec un identifiant
 * d'organisation — que la contrainte cherchait dans `users`. Tout ce qui crée une réservation
 * tombait en cascade, dont les vingt-quatre inspections qualité.
 *
 * ── CE QUI A TRANCHÉ ─────────────────────────────────────────────────────────────────────────
 *
 * Les `belongsTo()` déclarés dans les modèles, lus par `scripts/relations_declarees.php`. Sur les
 * 197 contraintes déduites du nom, 136 étaient vérifiables par un modèle ; CINQ le contredisaient.
 * Les cinq sont ici, avec le parent que le modèle désigne.
 *
 * ── LA LEÇON, ÉCRITE POUR LA PROCHAINE FOIS ──────────────────────────────────────────────────
 *
 * Le nom d'une colonne est une intention, pas un contrat. Un modèle qui déclare `belongsTo` dit ce
 * que le code fait vraiment. L'ordre correct est donc : interroger les modèles D'ABORD, et ne
 * déduire du nom que ce qu'aucun modèle ne déclare — l'inverse exact de ce qui a été fait ici.
 * C'est la même leçon que `getTable()`, qui avait déjà fait passer une centaine de tables pour
 * mortes dans ce même chantier.
 */
return new class extends Migration
{
    /**
     * [table, colonne, ancien parent (faux), nouveau parent, nom de contrainte].
     *
     * @var list<array{0:string,1:string,2:string,3:string,4:string}>
     */
    private array $corrections = [
        ['bookings', 'assigned_provider_organization_id', 'users', 'organization_accounts', 'fk_bookings_asgned_prov_org'],
        ['enterprise_work_orders', 'assigned_field_team_id', 'users', 'field_teams', 'fk_ent_work_orders_asgned_field_team'],
        ['enterprise_work_orders', 'assigned_service_partner_id', 'users', 'service_partners', 'fk_ent_work_orders_asgned_svc_partner'],
        ['mission_team_assignments', 'lead_assignment_id', 'users', 'mission_assignments', 'fk_mission_team_asg_lead_asg'],
        ['subscription_cycles_v2', 'invoice_id', 'invoices', 'subscription_invoices_v2', 'fk_sub_cycles_v2_invoice'],
    ];

    public function up(): void
    {
        foreach ($this->corrections as [$table, $colonne, , $parent, $nom]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $colonne) || ! Schema::hasTable($parent)) {
                continue;
            }

            $this->retirerLaContrainte($table, $colonne);

            // Même prudence que les migrations précédentes : une contrainte qui trouverait des
            // lignes sans parent est sautée, pas imposée. Ici le risque est réel — les valeurs
            // existantes désignaient la BONNE table depuis toujours, mais rien ne le garantit.
            if ($this->orphelins($table, $colonne, $parent) > 0) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($colonne, $parent, $nom) {
                $t->foreign($colonne, $nom)->references('id')->on($parent)->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->corrections as [$table, $colonne, $ancien, , $nom]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $colonne)) {
                continue;
            }

            $this->retirerLaContrainte($table, $colonne, $nom);

            if (Schema::hasTable($ancien) && $this->orphelins($table, $colonne, $ancien) === 0) {
                Schema::table($table, function (Blueprint $t) use ($colonne, $ancien) {
                    $t->foreign($colonne)->references('id')->on($ancien)->nullOnDelete();
                });
            }
        }
    }

    /**
     * Retire la contrainte portée par cette colonne, quel que soit son nom.
     *
     * On ne peut pas se contenter du nom attendu : la contrainte fausse a été posée par une autre
     * migration, sous un nom qu'elle a choisi. On demande donc au schéma lequel il porte.
     *
     * SQLite ne sait pas retirer une contrainte ; il reconstruit la table à chaque migration, si
     * bien qu'il n'y a rien à défaire — la suite repart d'un schéma neuf.
     */
    private function retirerLaContrainte(string $table, string $colonne, ?string $nomAttendu = null): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $noms = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $colonne)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME');

        foreach ($noms as $nom) {
            if ($nomAttendu !== null && $nom !== $nomAttendu) {
                continue;
            }

            Schema::table($table, fn (Blueprint $t) => $t->dropForeign($nom));
        }
    }

    private function orphelins(string $table, string $colonne, string $parent): int
    {
        return (int) DB::table($table)
            ->whereNotNull($table.'.'.$colonne)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from($parent)
                ->whereColumn($parent.'.id', $table.'.'.$colonne))
            ->count();
    }
};
