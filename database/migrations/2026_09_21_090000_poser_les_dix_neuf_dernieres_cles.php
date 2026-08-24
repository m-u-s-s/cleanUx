<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** LES DIX-NEUF DERNIÈRES CLÉS ÉTRANGÈRES QUE CE SCHÉMA POUVAIT PORTER. */
return new class extends Migration
{
    /**
     * [table, colonne, parent, nom court de la contrainte].
     *
     * @var list<array{0:string,1:string,2:string,3:string}>
     */
    private array $cles = [
        ['booking_reschedule_history', 'new_site_id', 'organization_sites', 'fk_brh_new_site'],
        ['booking_reschedule_history', 'old_site_id', 'organization_sites', 'fk_brh_old_site'],
        ['chat_participants', 'last_read_message_id', 'chat_messages', 'fk_chat_part_last_read_msg'],
        ['client_subscriptions', 'preferred_employee_id', 'users', 'fk_client_subs_pref_employee'],
        ['enterprise_booking_approvals', 'rejected_by_user_id', 'users', 'fk_ent_bk_appr_rejected_by'],
        ['enterprise_booking_approvals', 'final_approved_by_user_id', 'users', 'fk_ent_bk_appr_final_appr_by'],
        ['enterprise_work_orders', 'generated_batch_id', 'mission_batches', 'fk_ent_wo_generated_batch'],
        ['feedback', 'client_user_id', 'users', 'fk_feedback_client_user'],
        ['feedback', 'client_organization_id', 'organization_accounts', 'fk_feedback_client_org'],
        ['mission_batches', 'team_lead_user_id', 'users', 'fk_mission_batches_team_lead'],
        ['mission_dispute_signals', 'cancellation_id', 'booking_cancellations_v2', 'fk_mds_cancellation'],
        ['mission_dispute_signals', 'quote_revision_id', 'mission_quote_revisions', 'fk_mds_quote_revision'],
        ['mission_feature_suspensions', 'lifted_by_user_id', 'users', 'fk_mfs_lifted_by'],
        ['organization_sites', 'client_user_id', 'users', 'fk_org_sites_client_user'],
        ['personal_access_tokens', 'rotated_from_token_id', 'personal_access_tokens', 'fk_pat_rotated_from'],
        ['provider_profiles', 'kyc_last_verification_id', 'kyc_verifications', 'fk_prov_prof_kyc_last_verif'],
        ['provider_quotes', 'client_organization_id', 'organization_accounts', 'fk_prov_quotes_client_org'],
        ['trip_tracking_sessions', 'presence_confirmed_by_user_id', 'users', 'fk_tts_presence_confirmed_by'],
        ['users', 'referred_by_referral_id', 'referrals', 'fk_users_referred_by_referral'],
    ];

    public function up(): void
    {
        foreach ($this->cles as [$table, $colonne, $parent, $nom]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $colonne) || ! Schema::hasTable($parent)) {
                continue;
            }

            if ($this->dejaContrainte($table, $colonne)) {
                continue;
            }

            // UNE CONTRAINTE QUI TROUVERAIT DES LIGNES SANS PARENT EST SAUTÉE, PAS IMPOSÉE.
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
        if (DB::getDriverName() !== 'mysql') {
            // SQLite reconstruit la table à chaque migration : il n'y a rien à défaire.
            return;
        }

        foreach ($this->cles as [$table, , , $nom]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existe = DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('CONSTRAINT_NAME', $nom)
                ->exists();

            if ($existe) {
                Schema::table($table, fn (Blueprint $t) => $t->dropForeign($nom));
            }
        }
    }

    private function dejaContrainte(string $table, string $colonne): bool
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
