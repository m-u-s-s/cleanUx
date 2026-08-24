<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DIX-HUIT INDEX QUI NE SERVAIENT AUCUNE LECTURE. */
return new class extends Migration
{
    /**
     * [table, index redondant, ses colonnes, index qui le couvre].
     *
     * @var list<array{0:string,1:string,2:list<string>,3:string}>
     */
    private array $redondants = [
        ['catalog_translations', 'catalog_translation_lookup', ['translatable_type', 'translatable_id', 'locale'], 'catalog_translation_unique'],
        ['contract_rate_cards', 'contract_rate_cards_organization_contract_id_index', ['organization_contract_id'], 'contract_rate_card_unique'],
        ['contract_sla_events', 'contract_sla_events_mission_id_index', ['mission_id'], 'contract_sla_event_unique'],
        ['conversations', 'conversations_rendez_vous_id_index', ['rendez_vous_id'], 'conversations_rdv_type_unique'],
        ['country_service_catalog_rules', 'country_service_catalog_rules_country_id_index', ['country_id'], 'country_service_catalog_unique'],
        ['field_team_load_snapshots', 'field_team_load_snapshots_field_team_id_index', ['field_team_id'], 'ft_load_snapshots_team_date_unique'],
        ['limites_journalieres', 'limites_journalieres_user_id_index', ['user_id'], 'limites_journalieres_user_date_unique'],
        ['mission_team_assignments', 'mission_team_assignments_mission_id_index', ['mission_id'], 'mission_team_assignments_mission_id_field_team_id_index'],
        ['notifications', 'notifications_notifiable_type_notifiable_id_index', ['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_read_at_index'],
        ['organization_role_permissions', 'org_role_perm_lookup_idx', ['organization_account_id', 'role'], 'org_role_perm_unique'],
        ['partner_zone_coverages', 'partner_zone_coverages_service_partner_id_index', ['service_partner_id'], 'partner_zone_coverage_unique'],
        ['provider_profiles', 'provider_profiles_status_verification_status_index', ['status', 'verification_status'], 'provider_profiles_search_index'],
        ['rental_vehicles', 'rental_vehicles_category_index', ['category'], 'rental_vehicles_categorie_boite_index'],
        ['service_catalogs', 'service_catalogs_trade_active_index', ['trade_id', 'is_active'], 'svc_trade_active_sort_idx'],
        ['service_partner_load_snapshots', 'service_partner_load_snapshots_service_partner_id_index', ['service_partner_id'], 'service_partner_load_unique'],
        ['service_zone_postal_code', 'service_zone_postal_code_service_zone_id_index', ['service_zone_id'], 'sz_pc_unique'],
        ['subscriptions', 'subscriptions_billable_type_billable_id_index', ['billable_type', 'billable_id'], 'subscriptions_billable_type_billable_id_stripe_status_index'],
        ['translation_overrides', 'translation_overrides_locale_group_index', ['locale', 'group'], 'tx_overrides_unique'],
    ];

    public function up(): void
    {
        foreach ($this->redondants as [$table, $nom, $colonnes, $couvrant]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $index = collect(Schema::getIndexes($table))->keyBy('name');

            if (! $index->has($nom)) {
                continue;
            }

            $leCouvrant = $index->get($couvrant);

            // LA GARDE QUI COMPTE : sans son couvrant, l'index redondant ne l'est plus.
            if ($leCouvrant === null) {
                continue;
            }

            if (array_slice($leCouvrant['columns'], 0, count($colonnes)) !== $colonnes) {
                continue;
            }

            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($nom));
        }
    }

    public function down(): void
    {
        foreach ($this->redondants as [$table, $nom, $colonnes]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $index = collect(Schema::getIndexes($table))->keyBy('name');

            if ($index->has($nom)) {
                continue;
            }

            Schema::table($table, fn (Blueprint $t) => $t->index($colonnes, $nom));
        }
    }
};
