<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LES DIX-NEUF DERNIÈRES CLÉS ÉTRANGÈRES QUE CE SCHÉMA POUVAIT PORTER.
 *
 * ── D'OÙ VIENT CE CHIFFRE ────────────────────────────────────────────────────────────────────
 *
 * 111 colonnes `*_id` n'avaient aucune contrainte. Elles se répartissent ainsi :
 *
 *   65  identifiants EXTERNES (`varchar`) — Stripe, KYC. Ils désignent une ligne chez un tiers ;
 *       aucun parent local n'existe, il n'y a rien à contraindre.
 *   21  POLYMORPHES — une jumelle `*_type` décide de la table visée. Aucune clé étrangère ne sait
 *       exprimer « selon la valeur d'une autre colonne ».
 *   25  entières, réellement contraignables. Ce fichier en pose 19.
 *
 * ── COMMENT LE PARENT A ÉTÉ ÉTABLI ───────────────────────────────────────────────────────────
 *
 * Par le CODE qui écrit la colonne, jamais par son nom. La précaution n'est pas de principe : une
 * migration antérieure de ce chantier avait déduit cinq parents du nom, et LES CINQ étaient faux —
 * `assigned_provider_organization_id` désignait `users` parce que le préfixe `assigned_` passait
 * pour désigner une personne. Quarante-neuf tests étaient tombés.
 *
 * Chaque cible vient donc d'une affectation lue dans le code :
 *
 *   new_site_id / old_site_id      BookingRescheduleService:321   `?OrganizationSite`
 *   last_read_message_id           ChatService:254                `ChatMessage::query()`
 *   generated_batch_id             EnterpriseWorkOrderMissionGeneratorService:56  `$batch->id`
 *   cancellation_id                CancellationEngine:180         `BookingCancellationV2::create`
 *   quote_revision_id              QuoteRevisionArbiter:112       `$revision->id`
 *   kyc_last_verification_id       KycVerificationService:31      `KycVerification::create`
 *   referred_by_referral_id        ReferralService:89             `Referral::create`
 *   rotated_from_token_id          AuthRefreshController:70       `$oldToken->id` (auto-référence)
 *   presence_confirmed_by_user_id  PresenceCodeService:182        `$provider->id`
 *   rejected_by_user_id            EnterpriseBookingApprovalService:111  `$user->id`
 *
 * Les colonnes en `*_user_id` qu'aucun code n'écrit — `final_approved_by_user_id`,
 * `lifted_by_user_id`, `client_user_id`, `team_lead_user_id`, `preferred_employee_id` — désignent
 * une personne sans ambiguïté possible : ce schéma n'a pas d'autre table de personnes.
 *
 * Vérifié en outre sur les données : ZÉRO orphelin sur les vingt candidats examinés.
 *
 * ── TROIS ÉCARTÉES, ET POURQUOI ──────────────────────────────────────────────────────────────
 *
 * `api_token_usages.token_id` est la seule NOT NULL du lot, donc la seule qui exigerait autre chose
 * que `nullOnDelete`. Or `ApiTokenManager::revoke()` fait `$token->delete()`, et
 * `ApiAuthController:704` supprime TOUS les jetons d'un compte à la déconnexion. Une contrainte
 * restrictive casserait ces deux chemins dès la première ligne d'usage ; une cascade détruirait le
 * journal d'usage avec le jeton. Trancher relève de la rétention de données, pas du schéma.
 *
 * `country_service_catalog_rules.default_partner_id` et `default_team_id` : le modèle ne déclare
 * aucune relation pour elles, la table est VIDE, et rien ne les écrit hors d'un formulaire
 * d'administration. Il ne reste que le nom — exactement l'indice qui avait produit cinq erreurs.
 *
 * Restent hors périmètre, déjà documentées ailleurs : `audit_events.tenant_id` (un journal d'audit
 * doit survivre à la disparition de ce qu'il décrit, et son contenu est en réalité un
 * `organization_account_id`), `broadcast_events.audience_id` (aucun écrivain réel — seule une
 * fabrique y met un nombre au hasard) et `bookings.recurring_series_id` (trois notions dans une
 * seule colonne).
 *
 * ── COMPORTEMENT À LA SUPPRESSION ────────────────────────────────────────────────────────────
 *
 * `nullOnDelete` partout : les dix-neuf colonnes sont nullables, la valeur nulle y a déjà un sens,
 * et rien ne casse quand le parent disparaît. Une cascade effacerait des lignes que personne n'a
 * demandé d'effacer.
 */
return new class extends Migration
{
    /**
     * [table, colonne, parent, nom court de la contrainte].
     *
     * Noms explicites et courts : MySQL refuse un identifiant de plus de 64 caractères, et les noms
     * engendrés (`table_colonne_foreign`) frôlent la limite sur les tables au nom long.
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

            /*
             * UNE CONTRAINTE QUI TROUVERAIT DES LIGNES SANS PARENT EST SAUTÉE, PAS IMPOSÉE.
             *
             * Vérifié à zéro sur la base de travail, mais un autre environnement peut porter des
             * valeurs héritées. Y faire échouer la migration rendrait le déploiement impossible ;
             * la sauter laisse la colonne telle quelle, et l'audit du schéma le dira.
             */
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
