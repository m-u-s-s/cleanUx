<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LES CONTRAINTES QUE SEULS LES MODÈLES POUVAIENT DÉSIGNER.
 *
 * La migration précédente a posé les 197 clés étrangères dont le nom de colonne suffisait à
 * déduire la table parente (`commune_id` → `communes`). Il restait 61 colonnes dont le nom ne dit
 * rien de vrai :
 *
 *   `rendez_vous_id`         — la table `rendez_vous` N'EXISTE PLUS. Depuis la fusion, la colonne
 *                              pointe vers `bookings`, et dix tables la portent encore sous
 *                              l'ancien nom. Aucune déduction sur le nom ne pouvait le trouver.
 *   `sender_id`, `chosen_user_id`, `actor_user_id`, `preferred_provider_id` — tous `users`.
 *   `recurring_series_id`    — AUTO-RÉFÉRENCE : la réservation mère d'une série est une
 *                              réservation.
 *   `parent_zone_id`         — auto-référence aussi : la hiérarchie des zones.
 *
 * Ces 36 couples ne viennent pas d'une devinette mais des `belongsTo()` déclarés dans les modèles,
 * lus par `scripts/relations_declarees.php` (542 relations trouvées). C'est la même règle que pour
 * `getTable()` : la vérité est dans le code, jamais dans le nom.
 *
 * Les 25 colonnes qui restent après celle-ci n'ont AUCUNE relation déclarée nulle part. Elles ne
 * sont pas traitées ici : poser une contrainte vers une table devinée serait pire que ne rien
 * poser. Elles sont à examiner une par une.
 *
 * Même prudence que la migration précédente : chaque contrainte est précédée d'un comptage
 * d'orphelins, et sautée plutôt qu'imposée s'il en reste. Une migration qui casse un déploiement
 * n'est pas une amélioration.
 */
return new class extends Migration
{
    /**
     * Table => [[colonne, table parente, nullable, nom de contrainte], …].
     *
     * @var array<string, list<array{0:string,1:string,2:bool,3:string}>>
     */
    private array $contraintes = [
        /*
         * `bookings.recurring_series_id` A ÉTÉ RETIRÉE DE CETTE TABLE.
         *
         * Le modèle la déclare `belongsTo(Booking::class)`, ce qui l'avait fait entrer ici. La suite
         * complète l'a rejetée, et la cause est plus grave qu'un mauvais parent : cette colonne
         * reçoit TROIS choses différentes selon qui l'écrit — un UUID
         * (`CreateRecurringSeriesAction`), l'identifiant d'une `recurring_booking_series`
         * (`ProcessRecurringBookings`), et un identifiant de réservation selon le modèle. La colonne
         * est un `bigint unsigned` : le UUID n'y entre même pas.
         *
         * Aucune clé étrangère ne peut être juste tant que ces trois usages coexistent. Voir
         * `2026_09_15_090000_retirer_la_contrainte_sur_recurring_series_id`, qui la défait sur les
         * bases où elle a déjà été posée.
         */
        'cancellation_question_options' => [
            ['exempt_reason_id', 'cancellation_exempt_reasons', true, 'fk_cancel_q_options_exempt_reason'],
        ],
        'client_subscriptions' => [
            ['plan_id', 'subscription_plans', true, 'fk_client_subs_plan'],
        ],
        'complaint_cases' => [
            ['rendez_vous_id', 'bookings', true, 'fk_complaint_cases_rendez_vous'],
        ],
        'conversation_messages' => [
            ['sender_id', 'users', true, 'fk_conv_messages_sender'],
        ],
        'conversations' => [
            ['rendez_vous_id', 'bookings', true, 'fk_convs_rendez_vous'],
        ],
        'customer_claims' => [
            ['rendez_vous_id', 'bookings', true, 'fk_customer_claims_rendez_vous'],
        ],
        'enterprise_booking_approvals' => [
            ['finance_approved_by_user_id', 'users', true, 'fk_ent_booking_appr_finance_approved_by_user'],
            ['rendez_vous_id', 'bookings', true, 'fk_ent_booking_appr_rendez_vous'],
        ],
        'feedback' => [
            ['rendez_vous_id', 'bookings', true, 'fk_feedback_rendez_vous'],
        ],
        'field_teams' => [
            ['team_lead_user_id', 'users', true, 'fk_field_teams_team_lead_user'],
        ],
        'finance_invoices' => [
            ['rendez_vous_id', 'bookings', true, 'fk_finance_invoices_rendez_vous'],
        ],
        'finance_quotes' => [
            ['rendez_vous_id', 'bookings', true, 'fk_finance_quotes_rendez_vous'],
        ],
        'google_calendar_event_links' => [
            ['rendez_vous_id', 'bookings', true, 'fk_google_calendar_event_links_rendez_vous'],
        ],
        'incident_reports' => [
            ['rendez_vous_id', 'bookings', true, 'fk_inc_reports_rendez_vous'],
        ],
        'internal_assignment_decisions' => [
            ['chosen_user_id', 'users', true, 'fk_int_asg_decisions_chosen_user'],
        ],
        'mission_checklist_items' => [
            ['completed_by_user_id', 'users', true, 'fk_mission_chk_items_completed_by_user'],
        ],
        'mission_client_actions' => [
            ['client_user_id', 'users', true, 'fk_mission_client_actions_client_user'],
        ],
        'mission_events' => [
            ['actor_user_id', 'users', true, 'fk_mission_events_actor_user'],
        ],
        'mission_incidents' => [
            ['reported_by_user_id', 'users', true, 'fk_mission_inc_reported_by_user'],
        ],
        'mission_member_statuses' => [
            ['segment_assignment_id', 'mission_task_segment_assignments', true, 'fk_mission_member_stat_segment_asg'],
        ],
        'mission_quality_reviews' => [
            ['reviewer_user_id', 'users', true, 'fk_mission_qual_reviews_reviewer_user'],
        ],
        'mission_reports' => [
            ['generated_by_user_id', 'users', true, 'fk_mission_reports_generated_by_user'],
        ],
        'mission_tracking_points' => [
            ['tracking_session_id', 'mission_tracking_sessions', true, 'fk_mission_trk_points_trk_session'],
        ],
        'mission_tracking_sessions' => [
            ['assignment_id', 'mission_assignments', true, 'fk_mission_trk_sessions_asg'],
            ['employee_user_id', 'users', true, 'fk_mission_trk_sessions_employee_user'],
        ],
        'mission_verification_codes' => [
            ['validated_by_user_id', 'users', true, 'fk_mission_verif_codes_validated_by_user'],
        ],
        'organization_contracts' => [
            ['default_field_team_id', 'field_teams', true, 'fk_org_contracts_default_field_team'],
            ['default_service_partner_id', 'service_partners', true, 'fk_org_contracts_default_svc_partner'],
            ['provider_organization_id', 'organization_accounts', true, 'fk_org_contracts_prov_org'],
        ],
        'organization_sites' => [
            ['preferred_provider_id', 'users', true, 'fk_org_sites_preferred_prov'],
        ],
        'provider_quotes' => [
            ['client_user_id', 'users', true, 'fk_prov_quotes_client_user'],
        ],
        'service_zones' => [
            ['parent_zone_id', 'service_zones', true, 'fk_svc_zones_parent_zone'],
        ],
        'users' => [
            ['managed_service_zone_id', 'service_zones', true, 'fk_users_managed_svc_zone'],
            ['primary_service_zone_id', 'service_zones', true, 'fk_users_primary_svc_zone'],
        ],
        'work_order_approvals' => [
            ['approver_user_id', 'users', true, 'fk_work_order_appr_approver_user'],
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
