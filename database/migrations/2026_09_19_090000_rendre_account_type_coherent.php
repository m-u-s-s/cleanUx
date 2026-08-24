<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** `users.account_type` CONTREDISAIT `users.role`, SUR LA MÊME LIGNE. */
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
