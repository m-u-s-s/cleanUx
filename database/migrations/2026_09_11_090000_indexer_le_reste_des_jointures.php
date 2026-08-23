<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INDEXER LE RESTE DES COLONNES DE JOINTURE.
 *
 * Suite de `2026_09_10_090000_indexer_les_jointures_du_coeur_chaud`, qui a traité les six tables
 * les plus reliées. Celle-ci finit le travail : 118 colonnes sur 74 tables.
 *
 * ── DEUX FAMILLES, ET POURQUOI LES DEUX COMPTENT ─────────────────────────────────────────────
 *
 * 88 colonnes `bigint` : de vraies clés étrangères. Chaque jointure ou filtre sur l'une d'elles
 * balaie aujourd'hui la table entière.
 *
 * 30 colonnes `varchar` : presque toutes des CLÉS DE CORRÉLATION DE WEBHOOK —
 * `kyc_webhook_events.external_event_id`, `provider_wallet_transactions.stripe_transfer_id`,
 * `insurance_claims.external_claim_id`… À chaque événement reçu d'un fournisseur externe,
 * l'application cherche la ligne correspondante par cette colonne. Sans index, le coût de
 * traitement d'un webhook croît avec l'historique complet — c'est-à-dire indéfiniment.
 *
 * ── CE QU'ELLE NE FAIT PAS, DÉLIBÉRÉMENT ─────────────────────────────────────────────────────
 *
 * Elle ne pose aucun index UNIQUE. Plusieurs de ces colonnes le mériteraient : un
 * `external_event_id` unique rendrait l'idempotence des webhooks structurelle plutôt que
 * applicative. Mais l'unicité REFUSE des données, et la base de développement est vide — rien ici
 * ne permet de prouver qu'aucun doublon n'existe en production. Un index simple ne peut, lui,
 * jamais rien refuser. L'unicité est une décision distincte, à prendre sur des données réelles.
 *
 * Elle n'ajoute pas non plus les clés étrangères manquantes : même raison, une contrainte peut
 * refuser des lignes orphelines existantes.
 *
 * ── LES COLONNES POLYMORPHES N'Y SONT PAS ────────────────────────────────────────────────────
 *
 * Les 21 colonnes polymorphes du schéma (`subject_id`, `notifiable_id`, `source_id`…) portent
 * déjà l'index composite `(type, id)` qui leur convient — l'identifiant y est en SECONDE position,
 * ce qui est correct puisqu'une relation polymorphe se filtre toujours sur le couple. Une première
 * version de l'audit les réclamait toutes : 21 index simples redondants, sans effet sur la lecture
 * et coûteux à chaque écriture. `scripts/audit_schema.php` connaît désormais l'exception.
 */
return new class extends Migration
{
    /**
     * Table => [colonne => nom d'index].
     *
     * Noms écrits à la main plutôt que laissés à Laravel : `table_colonne_index` dépasse vite les
     * 64 caractères que MySQL accepte, et le plus long de ce schéma en fait DÉJÀ exactement 64.
     * SQLite, sur lequel tourne la suite, ne dirait rien du dépassement.
     *
     * @var array<string, array<string, string>>
     */
    private array $index = [
        'academy_courses' => [
            'trade_id' => 'ix_academy_courses_trade',
        ],
        'analytics_events' => [
            'anonymous_id' => 'ix_analytics_ev_anonymous',
        ],
        'audit_events' => [
            'service_zone_id' => 'ix_audit_ev_svc_zone',
        ],
        'booking_favorites' => [
            'service_zone_id' => 'ix_booking_favorites_svc_zone',
        ],
        'booking_insurances' => [
            'external_id' => 'ix_booking_inss_external',
        ],
        'booking_reschedule_history' => [
            'new_site_id' => 'ix_booking_resch_history_new_site',
            'old_site_id' => 'ix_booking_resch_history_old_site',
        ],
        'booking_tips' => [
            'stripe_transfer_id' => 'ix_booking_tips_stripe_transfer',
        ],
        'broadcast_events' => [
            'audience_id' => 'ix_broadcast_ev_audience',
        ],
        'cancellation_question_options' => [
            'exempt_reason_id' => 'ix_cancellation_question_options_exempt_reason',
        ],
        'chat_participants' => [
            'last_read_message_id' => 'ix_chat_participants_last_read_message',
        ],
        'client_places' => [
            'service_zone_id' => 'ix_client_places_svc_zone',
        ],
        'communes' => [
            'country_id' => 'ix_communes_country',
            'province_id' => 'ix_communes_province',
            'region_id' => 'ix_communes_region',
        ],
        'complaint_cases' => [
            'booking_id' => 'ix_complaint_cases_booking',
            'organization_account_id' => 'ix_complaint_cases_org_account',
            'rendez_vous_id' => 'ix_complaint_cases_rendez_vous',
        ],
        'country_service_catalog_rules' => [
            'default_partner_id' => 'ix_country_svc_catalog_rules_default_partner',
            'default_team_id' => 'ix_country_svc_catalog_rules_default_team',
        ],
        'dispute_resolutions' => [
            'stripe_refund_id' => 'ix_dispute_resolutions_stripe_refund',
        ],
        'email_logs' => [
            'previewed_by_user_id' => 'ix_email_logs_previewed_by_user',
        ],
        'email_webhook_events' => [
            'provider_message_id' => 'ix_email_wh_ev_prov_message',
        ],
        'enterprise_work_orders' => [
            'assigned_field_team_id' => 'ix_ent_work_orders_assigned_field_team',
            'assigned_service_partner_id' => 'ix_ent_work_orders_assigned_svc_partner',
            'organization_contract_id' => 'ix_ent_work_orders_org_contract',
            'requested_by_user_id' => 'ix_ent_work_orders_requested_by_user',
            'service_catalog_id' => 'ix_ent_work_orders_svc_catalog',
            'service_zone_id' => 'ix_ent_work_orders_svc_zone',
        ],
        'feedback' => [
            'employe_id' => 'ix_feedback_employe',
            'hidden_by_user_id' => 'ix_feedback_hidden_by_user',
            'mission_id' => 'ix_feedback_mission',
        ],
        'feedbacks' => [
            'rendez_vous_id' => 'ix_feedbacks_rendez_vous',
        ],
        'field_teams' => [
            'country_id' => 'ix_field_teams_country',
            'team_lead_user_id' => 'ix_field_teams_team_lead_user',
        ],
        'finance_credit_notes' => [
            'finance_invoice_id' => 'ix_finance_credit_notes_finance_invoice',
            'organization_account_id' => 'ix_finance_credit_notes_org_account',
        ],
        'google_calendar_connections' => [
            'calendar_id' => 'ix_gcal_connections_calendar',
            'google_user_id' => 'ix_gcal_connections_google_user',
        ],
        'google_calendar_event_links' => [
            'calendar_id' => 'ix_gcal_ev_links_calendar',
            'google_calendar_id' => 'ix_gcal_ev_links_gcal',
        ],
        'google_calendar_watch_channels' => [
            'calendar_id' => 'ix_gcal_watch_channels_calendar',
        ],
        'incident_reports' => [
            'organization_account_id' => 'ix_incident_reports_org_account',
            'rendez_vous_id' => 'ix_incident_reports_rendez_vous',
        ],
        'insurance_claims' => [
            'external_claim_id' => 'ix_ins_claims_external_claim',
        ],
        'insurance_webhook_events' => [
            'external_event_id' => 'ix_ins_wh_ev_external_ev',
        ],
        'internal_assignment_decisions' => [
            'chosen_user_id' => 'ix_internal_asg_decisions_chosen_user',
        ],
        'job_applications' => [
            'decided_by_user_id' => 'ix_job_appls_decided_by_user',
            'organization_invitation_id' => 'ix_job_appls_org_invitation',
            'user_id' => 'ix_job_appls_user',
        ],
        'job_postings' => [
            'created_by_user_id' => 'ix_job_postings_created_by_user',
            'provider_agency_id' => 'ix_job_postings_prov_agency',
            'trade_id' => 'ix_job_postings_trade',
        ],
        'kyc_checks' => [
            'external_id' => 'ix_kyc_checks_external',
        ],
        'kyc_verifications' => [
            'external_applicant_id' => 'ix_kyc_verifs_external_applicant',
        ],
        'kyc_webhook_events' => [
            'external_event_id' => 'ix_kyc_wh_ev_external_ev',
        ],
        'leave_requests' => [
            'decided_by_user_id' => 'ix_leave_requests_decided_by_user',
        ],
        'location_geocodes' => [
            'place_id' => 'ix_location_geocodes_place',
        ],
        'mission_checklist_items' => [
            'created_by_user_id' => 'ix_mission_checklist_items_created_by_user',
            'mission_media_id' => 'ix_mission_checklist_items_mission_media',
        ],
        'mission_checklists' => [
            'service_catalog_id' => 'ix_mission_checklists_svc_catalog',
        ],
        'mission_dispute_signals' => [
            'cancellation_id' => 'ix_mission_dispute_signals_cancellation',
            'quote_revision_id' => 'ix_mission_dispute_signals_quote_revision',
            'reviewed_by_user_id' => 'ix_mission_dispute_signals_reviewed_by_user',
        ],
        'mission_extras' => [
            'price_quote_id' => 'ix_mission_extras_price_quote',
            'stripe_payment_intent_id' => 'ix_mission_extras_stripe_payment_intent',
        ],
        'mission_feature_suspensions' => [
            'lifted_by_user_id' => 'ix_mission_feature_suspensions_lifted_by_user',
        ],
        'mission_incidents' => [
            'complaint_case_id' => 'ix_mission_incidents_complaint_case',
            'mission_media_id' => 'ix_mission_incidents_mission_media',
        ],
        'mission_quote_revisions' => [
            'top_up_payment_intent_id' => 'ix_mission_quote_revisions_top_up_payment_intent',
        ],
        'mission_reinforcement_requests' => [
            'assigned_to_user_id' => 'ix_mission_reinf_requests_assigned_to_user',
            'provider_team_id' => 'ix_mission_reinf_requests_prov_team',
            'requested_by_user_id' => 'ix_mission_reinf_requests_requested_by_user',
        ],
        'mission_team_assignments' => [
            'assigned_by_user_id' => 'ix_mission_team_asgs_assigned_by_user',
        ],
        'mission_time_settlements' => [
            'stripe_payment_intent_id' => 'ix_mission_time_setls_stripe_payment_intent',
        ],
        'mission_tracking_sessions' => [
            'employee_user_id' => 'ix_mission_tracking_sessions_employee_user',
        ],
        'mission_verification_codes' => [
            'validated_by_user_id' => 'ix_mission_verif_codes_validated_by_user',
        ],
        'order_drafts' => [
            'client_place_id' => 'ix_order_drafts_client_place',
        ],
        'organization_contracts' => [
            'default_field_team_id' => 'ix_org_contracts_default_field_team',
            'default_service_partner_id' => 'ix_org_contracts_default_svc_partner',
        ],
        'organization_site_budgets' => [
            'created_by_user_id' => 'ix_org_site_budgets_created_by_user',
            'organization_site_id' => 'ix_org_site_budgets_org_site',
        ],
        'organization_sites' => [
            'client_user_id' => 'ix_org_sites_client_user',
        ],
        'personal_access_tokens' => [
            'rotated_from_token_id' => 'ix_personal_access_tokens_rotated_from_token',
        ],
        'postal_codes' => [
            'commune_id' => 'ix_postal_codes_commune',
            'province_id' => 'ix_postal_codes_province',
            'region_id' => 'ix_postal_codes_region',
        ],
        'provider_agencies' => [
            'service_zone_id' => 'ix_prov_agencies_svc_zone',
        ],
        'provider_face_checks' => [
            'external_check_id' => 'ix_prov_face_checks_external_check',
        ],
        'provider_face_profiles' => [
            'external_face_id' => 'ix_prov_face_profiles_external_face',
        ],
        'provider_payouts' => [
            'provider_payout_id' => 'ix_prov_payouts_prov_payout',
        ],
        'provider_quote_lines' => [
            'booking_id' => 'ix_prov_quote_lines_booking',
            'service_catalog_id' => 'ix_prov_quote_lines_svc_catalog',
            'trade_id' => 'ix_prov_quote_lines_trade',
        ],
        'provider_quotes' => [
            'client_organization_id' => 'ix_prov_quotes_client_org',
            'created_by_user_id' => 'ix_prov_quotes_created_by_user',
            'organization_site_id' => 'ix_prov_quotes_org_site',
        ],
        'provider_wallet_transactions' => [
            'stripe_refund_id' => 'ix_prov_wallet_txs_stripe_refund',
            'stripe_transfer_id' => 'ix_prov_wallet_txs_stripe_transfer',
        ],
        'provinces' => [
            'country_id' => 'ix_provinces_country',
            'region_id' => 'ix_provinces_region',
        ],
        'regions' => [
            'country_id' => 'ix_regions_country',
        ],
        'safety_alerts' => [
            'acknowledged_by_user_id' => 'ix_safety_alerts_acknowledged_by_user',
            'booking_id' => 'ix_safety_alerts_booking',
            'mission_id' => 'ix_safety_alerts_mission',
        ],
        'service_partners' => [
            'country_id' => 'ix_svc_partners_country',
            'service_zone_id' => 'ix_svc_partners_svc_zone',
        ],
        'shifts' => [
            'field_team_id' => 'ix_shifts_field_team',
            'provider_agency_id' => 'ix_shifts_prov_agency',
        ],
        'sms_webhook_events' => [
            'external_event_id' => 'ix_sms_wh_ev_external_ev',
        ],
        'subscription_cycles_v2' => [
            'invoice_id' => 'ix_sub_cycles_v2_invoice',
        ],
        'subscription_invoices_v2' => [
            'stripe_invoice_id' => 'ix_sub_invoices_v2_stripe_invoice',
        ],
        'subscriptions_v2' => [
            'stripe_subscription_id' => 'ix_subs_v2_stripe_sub',
        ],
        'time_entries' => [
            'approved_by_user_id' => 'ix_time_entries_approved_by_user',
        ],
        'trip_tracking_sessions' => [
            'presence_confirmed_by_user_id' => 'ix_trip_tracking_sessions_presence_confirmed_by_user',
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

    /** La colonne est-elle déjà en tête d'un index ? On interroge le schéma plutôt que de supposer. */
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
