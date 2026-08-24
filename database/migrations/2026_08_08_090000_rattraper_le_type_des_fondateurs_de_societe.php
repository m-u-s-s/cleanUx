<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** RATTRAPE LES FONDATEURS INSCRITS DEPUIS LE MOBILE. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provider_profiles') || ! Schema::hasTable('organization_accounts')) {
            return;
        }

        $rattrapes = DB::table('provider_profiles')
            ->join(
                'organization_accounts',
                'organization_accounts.id',
                '=',
                'provider_profiles.organization_account_id'
            )
            ->where('provider_profiles.provider_type', 'company')
            ->where('organization_accounts.type', 'provider_company')
            ->update(['provider_profiles.provider_type' => 'company_worker']);

        if ($rattrapes > 0) {
            // Le compte est écrit dans la sortie de migration : un rattrapage silencieux sur des
            // comptes réels ne laisse aucune trace le jour où l'on cherche pourquoi.
            echo "  {$rattrapes} fondateur(s) de société prestataire retypé(s) en company_worker.".PHP_EOL;
        }
    }

    public function down(): void
    {
        // Volontairement vide — voir l'en-tête.
    }
};
