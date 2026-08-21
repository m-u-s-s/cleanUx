<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * POSER LES CLÉS ÉTRANGÈRES MANQUANTES — SANS JAMAIS REFUSER DE DONNÉES EXISTANTES.
 *
 * ── CE QU'ON A MESURÉ ────────────────────────────────────────────────────────────────────────
 *
 * 347 colonnes de jointure n'ont aucune contrainte. Toutes ne peuvent pas en recevoir une :
 *
 *   68 sont des identifiants EXTERNES (`stripe_transfer_id`, `external_event_id`,
 *      `google_user_id`…). Il n'existe aucune table parente locale : une clé étrangère n'a
 *      simplement pas de sens.
 *   ~82 sont POLYMORPHES (`source_id`, `actor_id`, `subject_id`…). Leur parent change d'une ligne
 *      à l'autre ; c'est la colonne `*_type` qui le désigne. Aucune contrainte ne peut exprimer
 *      cela.
 *   197 ont un parent identifiable et sont traitées ici.
 *
 * ── LE CONTRÔLE D'ORPHELINS, ET POURQUOI IL EXISTE ───────────────────────────────────────────
 *
 * Ajouter une contrainte à une colonne qui contient déjà des valeurs sans parent fait ÉCHOUER la
 * migration. La base de développement est vide : rien ici ne prouve qu'il n'en existe pas en
 * production. Une migration qui casse le déploiement n'est pas une amélioration.
 *
 * Chaque contrainte est donc précédée d'un comptage. S'il reste ne serait-ce qu'une ligne
 * orpheline, la contrainte est SAUTÉE et signalée — la migration continue, et le défaut de données
 * devient visible au lieu de bloquer la mise en ligne. On repassera dessus une fois les données
 * nettoyées.
 *
 * ── LE COMPORTEMENT À LA SUPPRESSION ─────────────────────────────────────────────────────────
 *
 * Colonne NULLABLE  → `nullOnDelete()`. Supprimer un utilisateur ne doit pas effacer l'historique
 *                     qu'il a produit : `closed_by_user_id` retombe à NULL, la mission reste.
 * Colonne NOT NULL  → comportement par défaut (`restrict`). La ligne fille n'a pas de sens sans
 *                     son parent ; refuser la suppression est la bonne réponse, et la seule que le
 *                     schéma puisse tenir sans inventer une valeur.
 *
 * ── CE QUE CELA APPORTE À L'ÉCHELLE VISÉE ────────────────────────────────────────────────────
 *
 * Sous InnoDB, une clé étrangère garantit qu'aucune ligne ne pointe dans le vide. À l'échelle d'un
 * seul pays cela se rattrape à la main ; sur plusieurs pays et tous les métiers, une poignée de
 * lignes orphelines fausse silencieusement chaque agrégat — facturation, statistiques, répartition.
 * La contrainte est le seul endroit où cette garantie ne peut pas être oubliée.
 */
return new class extends Migration
{
    /**
     * Table => [[colonne, table parente, nullable, nom de contrainte], …].
     *
     * Noms écrits à la main : MySQL refuse un identifiant de plus de 64 caractères, et le plus long
     * de ce schéma en fait déjà exactement 64. SQLite ne dirait rien du dépassement.
     *
     * @var array<string, list<array{0:string,1:string,2:bool,3:string}>>
     */
    private array $contraintes = [
        'academy_courses' => [
            ['trade_id', 'trades', true, 'fk_academy_courses_trade'],
        ],
        'activity_logs' => [
            ['service_zone_id', 'service_zones', true, 'fk_activity_logs_svc_zone'],
        ],
        'audit_events' => [
            ['service_zone_id', 'service_zones', true, 'fk_audit_events_svc_zone'],
        ],
        'availability_holds' => [
            ['booking_id', 'bookings', true, 'fk_availability_holds_booking'],
        ],
        'booking_cancellations_v2' => [
            ['booking_id', 'bookings', false, 'fk_booking_cancellations_v2_booking'],
        ],
        'booking_favorites' => [
            ['service_zone_id', 'service_zones', true, 'fk_booking_favorites_svc_zone'],
        ],
        'booking_insurances' => [
            ['booking_id', 'bookings', false, 'fk_booking_inss_booking'],
        ],
        'bookings' => [
            ['assigned_employee_id', 'users', true, 'fk_bookings_assigned_employee'],
            ['assigned_provider_organization_id', 'users', true, 'fk_bookings_assigned_prov_org'],
            ['client_id', 'users', true, 'fk_bookings_client'],
            ['client_place_id', 'client_places', true, 'fk_bookings_client_place'],
            ['employe_id', 'users', true, 'fk_bookings_employe'],
            ['organization_account_id', 'organization_accounts', true, 'fk_bookings_org_account'],
            ['organization_contract_id', 'organization_contracts', true, 'fk_bookings_org_contract'],
            ['postal_code_id', 'postal_codes', true, 'fk_bookings_postal_code'],
            ['provider_team_id', 'provider_teams', true, 'fk_bookings_prov_team'],
        ],
        'client_places' => [
            ['service_zone_id', 'service_zones', true, 'fk_client_places_svc_zone'],
        ],
        'client_subscriptions' => [
            ['client_id', 'users', true, 'fk_client_subs_client'],
            ['service_catalog_id', 'service_catalogs', true, 'fk_client_subs_svc_catalog'],
            ['service_zone_id', 'service_zones', true, 'fk_client_subs_svc_zone'],
        ],
        'communes' => [
            ['country_id', 'countries', true, 'fk_communes_country'],
            ['province_id', 'provinces', true, 'fk_communes_province'],
            ['region_id', 'regions', true, 'fk_communes_region'],
        ],
        'complaint_cases' => [
            ['booking_id', 'bookings', true, 'fk_complaint_cases_booking'],
            ['client_id', 'users', true, 'fk_complaint_cases_client'],
            ['organization_account_id', 'organization_accounts', true, 'fk_complaint_cases_org_account'],
            ['provider_user_id', 'users', true, 'fk_complaint_cases_prov_user'],
        ],
        'contract_rate_cards' => [
            ['organization_contract_id', 'organization_contracts', false, 'fk_contract_rate_cards_org_contract'],
            ['service_catalog_id', 'service_catalogs', false, 'fk_contract_rate_cards_svc_catalog'],
        ],
        'contract_sla_events' => [
            ['mission_id', 'missions', false, 'fk_contract_sla_events_mission'],
            ['organization_contract_id', 'organization_contracts', false, 'fk_contract_sla_events_org_contract'],
        ],
        'conversation_messages' => [
            ['conversation_id', 'conversations', true, 'fk_conv_messages_conv'],
        ],
        'conversations' => [
            ['booking_id', 'bookings', true, 'fk_convs_booking'],
            ['channel_id', 'channels', true, 'fk_convs_channel'],
            ['client_id', 'users', true, 'fk_convs_client'],
            ['employe_id', 'users', true, 'fk_convs_employe'],
            ['organization_account_id', 'organization_accounts', true, 'fk_convs_org_account'],
            ['organization_site_id', 'organization_sites', true, 'fk_convs_org_site'],
            ['provider_user_id', 'users', true, 'fk_convs_prov_user'],
        ],
        'country_billing_profiles' => [
            ['country_id', 'countries', true, 'fk_country_billing_profiles_country'],
        ],
        'country_operational_settings' => [
            ['country_id', 'countries', true, 'fk_country_operational_settings_country'],
        ],
        'country_service_catalog_rules' => [
            ['country_id', 'countries', false, 'fk_country_svc_catalog_rules_country'],
            ['service_catalog_id', 'service_catalogs', false, 'fk_country_svc_catalog_rules_svc_catalog'],
        ],
        'customer_claims' => [
            ['client_id', 'users', true, 'fk_customer_claims_client'],
        ],
        'disponibilites' => [
            ['user_id', 'users', true, 'fk_disponibilites_user'],
        ],
        'email_logs' => [
            ['previewed_by_user_id', 'users', true, 'fk_email_logs_previewed_by_user'],
        ],
        'enterprise_booking_approvals' => [
            ['approved_by_user_id', 'users', true, 'fk_ent_booking_approvals_approved_by_user'],
            ['booking_id', 'bookings', true, 'fk_ent_booking_approvals_booking'],
            ['manager_approved_by_user_id', 'users', true, 'fk_ent_booking_approvals_manager_approved_by_user'],
            ['organization_account_id', 'organization_accounts', true, 'fk_ent_booking_approvals_org_account'],
            ['organization_site_id', 'organization_sites', true, 'fk_ent_booking_approvals_org_site'],
            ['requested_by_user_id', 'users', true, 'fk_ent_booking_approvals_requested_by_user'],
        ],
        'enterprise_work_orders' => [
            ['assigned_field_team_id', 'users', true, 'fk_ent_work_orders_assigned_field_team'],
            ['assigned_service_partner_id', 'users', true, 'fk_ent_work_orders_assigned_svc_partner'],
            ['organization_contract_id', 'organization_contracts', true, 'fk_ent_work_orders_org_contract'],
            ['requested_by_user_id', 'users', true, 'fk_ent_work_orders_requested_by_user'],
            ['service_catalog_id', 'service_catalogs', true, 'fk_ent_work_orders_svc_catalog'],
            ['service_zone_id', 'service_zones', true, 'fk_ent_work_orders_svc_zone'],
        ],
        'feedback' => [
            ['booking_id', 'bookings', true, 'fk_feedback_booking'],
            ['client_id', 'users', true, 'fk_feedback_client'],
            ['employe_id', 'users', true, 'fk_feedback_employe'],
            ['hidden_by_user_id', 'users', true, 'fk_feedback_hidden_by_user'],
            ['mission_id', 'missions', true, 'fk_feedback_mission'],
        ],
        'field_team_load_snapshots' => [
            ['field_team_id', 'field_teams', true, 'fk_field_team_load_snapshots_field_team'],
        ],
        'field_teams' => [
            ['country_id', 'countries', true, 'fk_field_teams_country'],
            ['provider_agency_id', 'provider_agencies', true, 'fk_field_teams_prov_agency'],
            ['service_partner_id', 'service_partners', true, 'fk_field_teams_svc_partner'],
        ],
        'finance_credit_notes' => [
            ['finance_invoice_id', 'finance_invoices', true, 'fk_finance_credit_notes_finance_invoice'],
            ['organization_account_id', 'organization_accounts', true, 'fk_finance_credit_notes_org_account'],
        ],
        'finance_invoices' => [
            ['finance_quote_id', 'finance_quotes', true, 'fk_finance_invoices_finance_quote'],
        ],
        'fleet_assignments' => [
            ['booking_id', 'bookings', true, 'fk_fleet_asgs_booking'],
        ],
        'fleet_equipment' => [
            ['organization_account_id', 'organization_accounts', true, 'fk_fleet_equipment_org_account'],
        ],
        'fleet_vehicles' => [
            ['organization_account_id', 'organization_accounts', true, 'fk_fleet_vehicles_org_account'],
        ],
        'google_calendar_event_links' => [
            ['booking_id', 'bookings', true, 'fk_google_calendar_event_links_booking'],
            ['google_calendar_connection_id', 'google_calendar_connections', true, 'fk_google_calendar_event_links_google_calendar_connection'],
            ['mission_id', 'missions', true, 'fk_google_calendar_event_links_mission'],
            ['user_id', 'users', true, 'fk_google_calendar_event_links_user'],
        ],
        'incident_reports' => [
            ['client_id', 'users', true, 'fk_incident_reports_client'],
            ['employe_id', 'users', true, 'fk_incident_reports_employe'],
            ['organization_account_id', 'organization_accounts', true, 'fk_incident_reports_org_account'],
        ],
        'job_applications' => [
            ['decided_by_user_id', 'users', true, 'fk_job_appls_decided_by_user'],
            ['organization_invitation_id', 'organization_invitations', true, 'fk_job_appls_org_invitation'],
            ['user_id', 'users', true, 'fk_job_appls_user'],
        ],
        'job_postings' => [
            ['created_by_user_id', 'users', true, 'fk_job_postings_created_by_user'],
            ['provider_agency_id', 'provider_agencies', true, 'fk_job_postings_prov_agency'],
            ['trade_id', 'trades', true, 'fk_job_postings_trade'],
        ],
        'leave_requests' => [
            ['decided_by_user_id', 'users', true, 'fk_leave_requests_decided_by_user'],
        ],
        'limites_journalieres' => [
            ['employe_id', 'users', true, 'fk_limites_journalieres_employe'],
            ['user_id', 'users', true, 'fk_limites_journalieres_user'],
        ],
        'market_launch_readiness' => [
            ['country_id', 'countries', true, 'fk_market_launch_readiness_country'],
        ],
        'mission_batch_days' => [
            ['field_team_id', 'field_teams', true, 'fk_mission_batch_days_field_team'],
            ['mission_batch_id', 'mission_batches', true, 'fk_mission_batch_days_mission_batch'],
            ['service_partner_id', 'service_partners', true, 'fk_mission_batch_days_svc_partner'],
        ],
        'mission_batches' => [
            ['enterprise_work_order_id', 'enterprise_work_orders', true, 'fk_mission_batches_ent_work_order'],
            ['field_team_id', 'field_teams', true, 'fk_mission_batches_field_team'],
            ['organization_account_id', 'organization_accounts', true, 'fk_mission_batches_org_account'],
            ['organization_site_id', 'organization_sites', true, 'fk_mission_batches_org_site'],
            ['service_partner_id', 'service_partners', true, 'fk_mission_batches_svc_partner'],
        ],
        'mission_checklist_items' => [
            ['created_by_user_id', 'users', true, 'fk_mission_checklist_items_created_by_user'],
            ['mission_media_id', 'mission_media', true, 'fk_mission_checklist_items_mission_media'],
        ],
        'mission_checklists' => [
            ['service_catalog_id', 'service_catalogs', true, 'fk_mission_checklists_svc_catalog'],
        ],
        'mission_client_actions' => [
            ['mission_id', 'missions', true, 'fk_mission_client_actions_mission'],
        ],
        'mission_dispute_signals' => [
            ['reviewed_by_user_id', 'users', true, 'fk_mission_dispute_signals_reviewed_by_user'],
        ],
        'mission_extras' => [
            ['price_quote_id', 'price_quotes', true, 'fk_mission_extras_price_quote'],
        ],
        'mission_incidents' => [
            ['complaint_case_id', 'complaint_cases', true, 'fk_mission_incidents_complaint_case'],
            ['mission_media_id', 'mission_media', true, 'fk_mission_incidents_mission_media'],
            ['resolved_by_user_id', 'users', true, 'fk_mission_incidents_resolved_by_user'],
        ],
        'mission_media' => [
            ['uploaded_by_user_id', 'users', true, 'fk_mission_media_uploaded_by_user'],
        ],
        'mission_member_statuses' => [
            ['field_team_id', 'field_teams', true, 'fk_mission_member_stat_field_team'],
            ['mission_id', 'missions', true, 'fk_mission_member_stat_mission'],
            ['mission_task_segment_id', 'mission_task_segments', true, 'fk_mission_member_stat_mission_task_seg'],
            ['user_id', 'users', true, 'fk_mission_member_stat_user'],
        ],
        'mission_partner_assignments' => [
            ['mission_id', 'missions', true, 'fk_mission_partner_asgs_mission'],
            ['service_partner_id', 'service_partners', true, 'fk_mission_partner_asgs_svc_partner'],
        ],
        'mission_quality_inspections' => [
            ['booking_id', 'bookings', true, 'fk_mission_quality_inspections_booking'],
            ['mission_id', 'missions', true, 'fk_mission_quality_inspections_mission'],
        ],
        'mission_reinforcement_requests' => [
            ['assigned_to_user_id', 'users', true, 'fk_mission_reinf_requests_assigned_to_user'],
            ['field_team_id', 'field_teams', true, 'fk_mission_reinf_requests_field_team'],
            ['mission_batch_day_id', 'mission_batch_days', true, 'fk_mission_reinf_requests_mission_batch_day'],
            ['mission_batch_id', 'mission_batches', true, 'fk_mission_reinf_requests_mission_batch'],
            ['mission_id', 'missions', true, 'fk_mission_reinf_requests_mission'],
            ['mission_task_segment_id', 'mission_task_segments', true, 'fk_mission_reinf_requests_mission_task_seg'],
            ['provider_team_id', 'provider_teams', true, 'fk_mission_reinf_requests_prov_team'],
            ['requested_by_user_id', 'users', true, 'fk_mission_reinf_requests_requested_by_user'],
            ['resolved_by_user_id', 'users', true, 'fk_mission_reinf_requests_resolved_by_user'],
            ['service_partner_id', 'service_partners', true, 'fk_mission_reinf_requests_svc_partner'],
        ],
        'mission_task_segment_assignments' => [
            ['assigned_by_user_id', 'users', true, 'fk_mission_task_seg_asgs_assigned_by_user'],
            ['field_team_id', 'field_teams', true, 'fk_mission_task_seg_asgs_field_team'],
            ['mission_id', 'missions', true, 'fk_mission_task_seg_asgs_mission'],
            ['mission_task_segment_id', 'mission_task_segments', true, 'fk_mission_task_seg_asgs_mission_task_seg'],
            ['user_id', 'users', true, 'fk_mission_task_seg_asgs_user'],
        ],
        'mission_task_segments' => [
            ['assigned_user_id', 'users', true, 'fk_mission_task_segs_assigned_user'],
            ['field_team_id', 'field_teams', true, 'fk_mission_task_segs_field_team'],
            ['mission_batch_day_id', 'mission_batch_days', true, 'fk_mission_task_segs_mission_batch_day'],
            ['mission_batch_id', 'mission_batches', true, 'fk_mission_task_segs_mission_batch'],
            ['mission_id', 'missions', true, 'fk_mission_task_segs_mission'],
            ['service_partner_id', 'service_partners', true, 'fk_mission_task_segs_svc_partner'],
        ],
        'mission_team_assignments' => [
            ['assigned_by_user_id', 'users', true, 'fk_mission_team_asgs_assigned_by_user'],
            ['field_team_id', 'field_teams', false, 'fk_mission_team_asgs_field_team'],
            ['lead_assignment_id', 'users', true, 'fk_mission_team_asgs_lead_asg'],
            ['mission_id', 'missions', false, 'fk_mission_team_asgs_mission'],
        ],
        'missions' => [
            ['closed_by_user_id', 'users', true, 'fk_missions_closed_by_user'],
            ['field_team_id', 'field_teams', true, 'fk_missions_field_team'],
            ['lead_employee_id', 'users', true, 'fk_missions_lead_employee'],
            ['organization_account_id', 'organization_accounts', true, 'fk_missions_org_account'],
            ['organization_contract_id', 'organization_contracts', true, 'fk_missions_org_contract'],
            ['organization_site_id', 'organization_sites', true, 'fk_missions_org_site'],
            ['provider_agency_id', 'provider_agencies', true, 'fk_missions_prov_agency'],
            ['service_catalog_id', 'service_catalogs', true, 'fk_missions_svc_catalog'],
            ['service_zone_id', 'service_zones', true, 'fk_missions_svc_zone'],
            ['started_by_user_id', 'users', true, 'fk_missions_started_by_user'],
        ],
        'order_drafts' => [
            ['client_place_id', 'client_places', true, 'fk_order_drafts_client_place'],
        ],
        'organization_accounts' => [
            ['commune_id', 'communes', true, 'fk_org_accounts_commune'],
            ['country_id', 'countries', true, 'fk_org_accounts_country'],
            ['postal_code_id', 'postal_codes', true, 'fk_org_accounts_postal_code'],
            ['province_id', 'provinces', true, 'fk_org_accounts_province'],
            ['region_id', 'regions', true, 'fk_org_accounts_region'],
        ],
        'organization_contracts' => [
            ['country_id', 'countries', true, 'fk_org_contracts_country'],
            ['service_zone_id', 'service_zones', true, 'fk_org_contracts_svc_zone'],
        ],
        'organization_members' => [
            ['provider_agency_id', 'provider_agencies', true, 'fk_org_members_prov_agency'],
        ],
        'organization_site_budgets' => [
            ['created_by_user_id', 'users', true, 'fk_org_site_budgets_created_by_user'],
            ['organization_site_id', 'organization_sites', true, 'fk_org_site_budgets_org_site'],
        ],
        'partner_zone_coverages' => [
            ['country_id', 'countries', true, 'fk_partner_zone_coverages_country'],
            ['service_catalog_id', 'service_catalogs', true, 'fk_partner_zone_coverages_svc_catalog'],
            ['service_partner_id', 'service_partners', false, 'fk_partner_zone_coverages_svc_partner'],
            ['service_zone_id', 'service_zones', true, 'fk_partner_zone_coverages_svc_zone'],
        ],
        'postal_codes' => [
            ['commune_id', 'communes', true, 'fk_postal_codes_commune'],
            ['province_id', 'provinces', true, 'fk_postal_codes_province'],
            ['region_id', 'regions', true, 'fk_postal_codes_region'],
        ],
        'price_quotes' => [
            ['booking_id', 'bookings', true, 'fk_price_quotes_booking'],
        ],
        'provider_agencies' => [
            ['service_zone_id', 'service_zones', true, 'fk_prov_agencies_svc_zone'],
        ],
        'provider_quote_lines' => [
            ['booking_id', 'bookings', true, 'fk_prov_quote_lines_booking'],
            ['service_catalog_id', 'service_catalogs', true, 'fk_prov_quote_lines_svc_catalog'],
            ['trade_id', 'trades', false, 'fk_prov_quote_lines_trade'],
        ],
        'provider_quotes' => [
            ['created_by_user_id', 'users', true, 'fk_prov_quotes_created_by_user'],
            ['organization_site_id', 'organization_sites', true, 'fk_prov_quotes_org_site'],
        ],
        'provinces' => [
            ['country_id', 'countries', true, 'fk_provinces_country'],
            ['region_id', 'regions', true, 'fk_provinces_region'],
        ],
        'regions' => [
            ['country_id', 'countries', true, 'fk_regions_country'],
        ],
        'safety_alerts' => [
            ['acknowledged_by_user_id', 'users', true, 'fk_safety_alerts_acknowledged_by_user'],
            ['booking_id', 'bookings', true, 'fk_safety_alerts_booking'],
            ['mission_id', 'missions', true, 'fk_safety_alerts_mission'],
        ],
        'service_partner_load_snapshots' => [
            ['service_partner_id', 'service_partners', true, 'fk_svc_partner_load_snapshots_svc_partner'],
        ],
        'service_partners' => [
            ['country_id', 'countries', true, 'fk_svc_partners_country'],
            ['service_zone_id', 'service_zones', true, 'fk_svc_partners_svc_zone'],
        ],
        'service_zone_postal_code' => [
            ['postal_code_id', 'postal_codes', false, 'fk_svc_zone_postal_code_postal_code'],
            ['service_zone_id', 'service_zones', false, 'fk_svc_zone_postal_code_svc_zone'],
        ],
        'service_zones' => [
            ['commune_id', 'communes', true, 'fk_svc_zones_commune'],
            ['province_id', 'provinces', true, 'fk_svc_zones_province'],
            ['region_id', 'regions', true, 'fk_svc_zones_region'],
        ],
        'shifts' => [
            ['field_team_id', 'field_teams', true, 'fk_shifts_field_team'],
            ['provider_agency_id', 'provider_agencies', true, 'fk_shifts_prov_agency'],
        ],
        'subscription_cycles_v2' => [
            ['invoice_id', 'invoices', true, 'fk_sub_cycles_v2_invoice'],
        ],
        'subscriptions' => [
            ['user_id', 'users', true, 'fk_subs_user'],
        ],
        'time_entries' => [
            ['approved_by_user_id', 'users', true, 'fk_time_entries_approved_by_user'],
        ],
        'users' => [
            ['organization_account_id', 'organization_accounts', true, 'fk_users_org_account'],
            ['postal_code_id', 'postal_codes', true, 'fk_users_postal_code'],
        ],
    ];

    /** @var list<string> Ce qu'on n'a pas pu poser, et pourquoi. */
    private array $sautees = [];

    public function up(): void
    {
        foreach ($this->contraintes as $table => $liste) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($liste as [$colonne, $parent, $nullable, $nom]) {
                if (! Schema::hasColumn($table, $colonne) || ! Schema::hasTable($parent)) {
                    continue;
                }

                if ($this->contrainteExiste($table, $colonne)) {
                    continue;
                }

                if (($orphelins = $this->orphelins($table, $colonne, $parent)) > 0) {
                    $this->sautees[] = "{$table}.{$colonne} → {$parent} : {$orphelins} ligne(s) orpheline(s)";

                    continue;
                }

                Schema::table($table, function (Blueprint $t) use ($colonne, $parent, $nullable, $nom) {
                    $cle = $t->foreign($colonne, $nom)->references('id')->on($parent);

                    if ($nullable) {
                        $cle->nullOnDelete();
                    }
                });
            }
        }

        foreach ($this->sautees as $ligne) {
            // Visible dans la sortie de `migrate`, et donc dans les journaux de déploiement.
            echo "  [clé étrangère sautée] {$ligne}\n";
        }
    }

    public function down(): void
    {
        foreach ($this->contraintes as $table => $liste) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($liste as [$colonne, , , $nom]) {
                if ($this->contrainteExiste($table, $colonne)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropForeign($nom));
                }
            }
        }
    }

    /**
     * Combien de lignes pointent vers un parent qui n'existe pas ?
     *
     * NULL n'est pas orphelin : une colonne nullable vide est un « pas de lien », que la contrainte
     * accepte. Seule une valeur renseignée sans parent correspondant bloquerait la migration.
     */
    private function orphelins(string $table, string $colonne, string $parent): int
    {
        return (int) DB::table($table)
            ->whereNotNull($table.'.'.$colonne)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from($parent)
                ->whereColumn($parent.'.id', $table.'.'.$colonne))
            ->count();
    }

    private function contrainteExiste(string $table, string $colonne): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $colonne)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};
