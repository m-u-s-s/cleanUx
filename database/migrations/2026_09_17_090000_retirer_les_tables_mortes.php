<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NEUF TABLES QUE PLUS RIEN N'ATTEIGNAIT.
 *
 * ── COMMENT ON LES A TROUVÉES, ET COMMENT ON S'EST TROMPÉ D'ABORD ────────────────────────────
 *
 * `scripts/audit_schema.php` retenait un critère simple : ni modèle Eloquent, ni mention littérale
 * dans `app`, `routes`, `config`, `resources/views`. Il rendait neuf tables. Ce critère s'est révélé
 * faux DANS LES DEUX SENS, et les deux erreurs valaient la peine d'être trouvées :
 *
 *   TROP LARGE — il ne cherchait un modèle que dans `app/Models`, jamais dans `vendor/`.
 *   `subscription_items` appartient à laravel/cashier et `team_user` à laravel/jetstream, tous deux
 *   installés ici. Les supprimer aurait cassé les abonnements Stripe et les équipes. Elles ne sont
 *   PAS dans cette migration.
 *
 *   TROP ÉTROIT — une correspondance littérale suffisait à sauver une table. `invoices`, `payments`
 *   et `feedbacks` passaient pour vivantes parce que ces mots servent partout de clé de tableau ou
 *   de libellé d'écran — `'invoices' => $factures`, `ReportTile::make('invoices', …)`,
 *   `$registry->register('feedbacks', …)`. Aucune de ces lignes ne touche la table. Vérifié une par
 *   une : la donnée vient toujours de `FinanceInvoice`, `FinancePayment` ou du modèle `Feedback`,
 *   qui pointe sur `feedback` au singulier.
 *
 * ── CE QUI REMPLACE CHACUNE ──────────────────────────────────────────────────────────────────
 *
 * Aucune n'est supprimée pour cause de vide : elles sont supprimées parce qu'une AUTRE table tient
 * la même notion, avec un modèle et des appelants réels.
 *
 *   invoices, invoice_items, payments   → finance_invoices / finance_payments / finance_reminders,
 *                                         modèle FinanceInvoice. Une conception de facturation
 *                                         complète en doublait une autre, sans jamais servir.
 *   feedbacks                           → feedback. La seconde a 34 colonnes, la première 15, et
 *                                         chacune des 15 existe dans l'autre : un sous-ensemble
 *                                         strict. Le modèle Feedback emploie `feedback`.
 *   provider_daily_limits               → limites_journalieres, modèle LimiteJournaliere.
 *                                         `LimitesJournaliereSeeder:16` choisit déjà la seconde
 *                                         quand elle existe.
 *   provider_availabilities             → disponibilites (modèle Disponibilite) et
 *                                         availability_slots (28 lignes, la seule renseignée).
 *   provider_team_members               → field_team_members, modèle FieldTeamMember.
 *   account_subscriptions               → subscriptions_v2, modèle SubscriptionV2 : même notion, en
 *                                         beaucoup plus riche (cycles, devise, essai, pause, échecs de
 *                                         prélèvement), avec contrôleur, commande, centre admin et
 *                                         écran client. Ce n'est PAS `client_subscriptions`, qui tient
 *                                         une tout autre chose : l'abonnement de SERVICE
 *                                         (`day_of_week`, `heure`, `service_catalog_id`) — « un
 *                                         nettoyage tous les mardis ». Réserve honnête : la table
 *                                         retirée portait `organization_account_id`, absent de
 *                                         `subscriptions_v2` ; l'abonnement au nom d'une SOCIÉTÉ
 *                                         n'est donc exprimable nulle part. Mais il ne l'était pas
 *                                         davantage ici — zéro ligne, zéro modèle, zéro appelant :
 *                                         on retire une intention jamais réalisée, pas une capacité.
 *   google_calendar_events              → l'intégration passe par google_calendar_connections,
 *                                         google_calendar_event_links et
 *                                         google_calendar_watch_channels, qui ont TOUS un modèle.
 *                                         On ne recopie pas localement les événements de Google :
 *                                         on lie les siens aux nôtres.
 *
 * ── CE QUI N'EST PAS SUPPRIMÉ ────────────────────────────────────────────────────────────────
 *
 * `booking_status_histories` sortait du même audit, et c'est le cas inverse : AUCUNE autre table ne
 * tient l'historique des statuts de réservation. Le modèle `Booking` ne porte pas le trait d'audit
 * générique, et `BookingObserver` n'en garde pas trace. Cette table est un manque, pas un doublon —
 * elle se branche, elle ne se supprime pas.
 *
 * ── SÉCURITÉ D'EXÉCUTION ─────────────────────────────────────────────────────────────────────
 *
 * Chaque table est vérifiée VIDE au moment de la suppression. Une table qui aurait reçu des lignes
 * entre-temps est SAUTÉE, jamais vidée : la migration renonce plutôt que de détruire une donnée
 * qu'elle n'attendait pas. `payments` et `invoice_items` partent avant `invoices`, qu'elles
 * contraignent.
 */
return new class extends Migration
{
    /** L'ordre compte : ce qui contraint part avant ce qui est contraint. */
    private array $tables = [
        'invoice_items',
        'payments',
        'invoices',
        'feedbacks',
        'provider_daily_limits',
        'provider_availabilities',
        'provider_team_members',
        'account_subscriptions',
        'google_calendar_events',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (DB::table($table)->exists()) {
                // Quelqu'un s'en sert désormais : on ne tranche pas à sa place.
                continue;
            }

            Schema::drop($table);
        }
    }

    public function down(): void
    {
        // Volontairement vide. Ces tables sont créées par leurs migrations d'origine, qui restent en
        // place : un `migrate:fresh` les recrée puis celle-ci les retire. Les redéclarer ici
        // dupliquerait leur définition et ferait diverger les deux copies au premier changement.
    }
};
