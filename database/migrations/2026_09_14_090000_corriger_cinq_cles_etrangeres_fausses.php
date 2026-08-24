<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** CINQ CLÉS ÉTRANGÈRES QUI POINTAIENT VERS LA MAUVAISE TABLE. */
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

    /** Retire la contrainte portée par cette colonne, quel que soit son nom. */
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
