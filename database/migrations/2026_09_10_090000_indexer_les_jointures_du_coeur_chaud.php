<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** INDEXER LES COLONNES DE JOINTURE DU CŒUR CHAUD. */
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

    /** La colonne est-elle DÉJÀ en tête d'un index ? */
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
