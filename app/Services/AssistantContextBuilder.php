<?php

namespace App\Services;

use App\Enums\AssistantContextRole;
use App\Models\User;
use Illuminate\Support\Collection;
use App\Services\Assistant\Stats\AssistantStats;

/**
 * Construit le contexte dynamique pour le chatbot CleanUx.
 *
 * Chaque rôle reçoit un system prompt différent, enrichi
 * avec les données temps réel de l'utilisateur.
 */
class AssistantContextBuilder
{
    // ──────────────────────────────────────────────────────
    // Point d'entrée principal
    // ──────────────────────────────────────────────────────

    public function build(User $user): array
    {
        $contextRole = $user->assistantContextRole();

        return [
            'system'  => $this->buildSystemPrompt($contextRole, $user),
            'context' => $this->buildContextData($contextRole, $user),
            'tools'   => $this->availableActions($contextRole),
        ];
    }

    // ──────────────────────────────────────────────────────
    // System prompts par rôle
    // ──────────────────────────────────────────────────────

    private function buildSystemPrompt(AssistantContextRole $role, User $user): string
    {
        $base = $this->basePrompt($user);
        $ctx  = $this->buildContextData($role, $user);

        return match ($role) {

            // ── Particulier ──────────────────────────────
            AssistantContextRole::CLIENT_PERSONAL => $base . "
Tu assistes {$user->name} en tant que client particulier CleanUx.

Contexte actuel :
- Prochaine mission : " . ($ctx['next_booking'] ?? 'Aucune planifiée') . "
- Mission en cours : " . ($ctx['active_mission'] ?? 'Aucune') . "
- Solde crédits : " . ($ctx['credits'] ?? '0') . " €

Tu peux aider avec :
✓ Réserver un nettoyage (en plusieurs étapes guidées)
✓ Suivre l'avancement d'une mission en cours
✓ Comprendre une facture
✓ Modifier ou annuler une réservation
✓ Signaler un problème après une mission
✓ Activer un code promo

Quand l'utilisateur veut réserver, guide-le étape par étape :
1. Quel type de lieu ? (appartement, maison, bureau)
2. Quelle surface aproximative ?
3. Quelle date et heure ?
Propose ensuite d'exécuter la réservation avec sa confirmation.",

            // ── Entreprise cliente ───────────────────────
            AssistantContextRole::CLIENT_COMPANY => $base . "
Tu assistes {$user->name} en tant que gestionnaire entreprise CleanUx.
Organisation : " . ($ctx['org_name'] ?? 'N/A') . "
Ton rôle dans l'org : " . ($ctx['member_role'] ?? 'N/A') . "

Contexte actuel :
- Locaux enregistrés : " . ($ctx['sites_count'] ?? 0) . "
- Missions actives ce mois : " . ($ctx['active_missions'] ?? 0) . "
- Demandes en attente d'approbation : " . ($ctx['pending_approvals'] ?? 0) . "
- Factures impayées : " . ($ctx['unpaid_invoices'] ?? 0) . "

Tu peux aider avec :
✓ Créer une demande de nettoyage pour un local
✓ Voir l'état des demandes en attente
✓ Enregistrer un nouveau local
✓ Inviter un membre à l'organisation
✓ Expliquer une facture ou un devis
✓ Voir le planning des interventions

Pour les demandes multi-sites, demande d'abord quel local est concerné.",

            // ── Prestataire indépendant ──────────────────
            AssistantContextRole::PROVIDER_INDEPENDENT => $base . "
Tu assistes {$user->name}, nettoyeur indépendant sur CleanUx.

Contexte aujourd'hui :
- Missions du jour : " . ($ctx['today_missions'] ?? 'Aucune') . "
- Prochaine mission : " . ($ctx['next_mission'] ?? 'Aucune') . "
- Statut Stripe Connect : " . ($ctx['stripe_status'] ?? 'Non configuré') . "
- Note moyenne : " . ($ctx['avg_rating'] ?? 'N/A') . "/5

Tu peux aider avec :
✓ Expliquer comment démarrer/terminer une mission
✓ Signaler un incident ou un problème sur site
✓ Gérer ses disponibilités
✓ Comprendre ses paiements et virements Stripe
✓ Comment améliorer sa note
✓ Procédure en cas de litige avec un client",

            // ── Employé en société ───────────────────────
            AssistantContextRole::PROVIDER_COMPANY => $base . "
Tu assistes {$user->name}, " . ($ctx['member_role'] ?? 'membre') . " chez " . ($ctx['org_name'] ?? 'son entreprise') . ".

Contexte aujourd'hui :
- Mes missions assignées : " . ($ctx['my_missions_today'] ?? 'Aucune') . "
- Équipe : " . ($ctx['team_name'] ?? 'Non assigné') . "
- Chef d'équipe : " . ($ctx['team_lead'] ?? 'N/A') . "
- Messages non lus : " . ($ctx['unread_messages'] ?? 0) . "
- Tâches assignées : " . ($ctx['pending_tasks'] ?? 0) . "

Tu peux aider avec :
✓ Voir le détail d'une mission assignée
✓ Comment pointer arrivée / départ (QR ou code)
✓ Signaler un incident à son chef d'équipe
✓ Voir les tâches assignées
✓ Comprendre les canaux de communication
✓ Contacter son dispatcher en cas de problème",

            // ── Admin ────────────────────────────────────
            AssistantContextRole::ADMIN => $base . "
Tu assistes un administrateur CleanUx.

Statistiques plateforme :
- Utilisateurs actifs : " . ($ctx['total_users'] ?? 'N/A') . "
- Missions en cours : " . ($ctx['active_missions'] ?? 'N/A') . "
- Revenus ce mois : " . ($ctx['monthly_revenue'] ?? 'N/A') . " €
- Alertes actives : " . ($ctx['alerts'] ?? 0) . "

Tu peux aider avec :
✓ Interroger les statistiques en langage naturel
✓ Expliquer des commandes artisan
✓ Identifier des anomalies (prestataires sans Stripe, bookings bloqués)
✓ Générer des rapports textuels
✓ Répondre aux questions sur la configuration

Ne jamais exécuter d'actions destructives sans confirmation explicite.",
        };
    }

    // ──────────────────────────────────────────────────────
    // Données contextuelles dynamiques
    // ──────────────────────────────────────────────────────

    private function buildContextData(AssistantContextRole $role, User $user): array
    {
        $stats = app(AssistantStats::class)->forUser($user);

        return match ($role) {
            AssistantContextRole::CLIENT_PERSONAL => [
                'next_booking'   => $this->nextBookingLabel($user),
                'active_mission' => $this->activeMissionLabel($user),
                'credits'        => $user->customerProfile?->plan_type ?? 'standard',
            ],

            AssistantContextRole::CLIENT_COMPANY => [
                'org_name'          => $user->currentOrganization?->name,
                'member_role'       => $user->membershipIn()?->role?->label(),
                'sites_count'       => $user->currentOrganization?->sites()->count() ?? 0,
                'active_missions'   => $stats['active_missions'],
                'pending_approvals' => $stats['pending_approvals'],
                'unpaid_invoices'   => $stats['unpaid_invoices'],
            ],

            AssistantContextRole::PROVIDER_INDEPENDENT => [
                'today_missions' => $this->todayMissionsLabel($user),
                'next_mission'   => $this->nextMissionLabel($user),
                'stripe_status'  => $user->providerProfile?->stripe_connect_status ?? 'not_connected',
                'avg_rating'     => $stats['avg_rating'],
            ],

            AssistantContextRole::PROVIDER_COMPANY => [
                'org_name'          => $user->currentOrganization?->name,
                'member_role'       => $user->membershipIn()?->role?->label(),
                'my_missions_today' => $this->todayMissionsLabel($user),
                'team_name'         => $stats['team_name'],
                'team_lead'         => null,
                'unread_messages'   => 0,
                'pending_tasks'     => $stats['pending_tasks'],
            ],

            AssistantContextRole::ADMIN => [
                'total_users'     => \App\Models\User::count(),
                'active_missions' => $stats['admin_total_active_missions'],
                'monthly_revenue' => $stats['admin_monthly_revenue'],
                'alerts'          => $stats['admin_alerts'],
            ],
        };
    }

    // ──────────────────────────────────────────────────────
    // Actions disponibles par rôle (pour assistant_actions)
    // ──────────────────────────────────────────────────────

    private function availableActions(AssistantContextRole $role): array
    {
        return match ($role) {
            AssistantContextRole::CLIENT_PERSONAL => [
                'create_booking',
                'cancel_booking',
                'explain_invoice',
                'report_issue',
            ],
            AssistantContextRole::CLIENT_COMPANY => [
                'create_booking',
                'approve_booking',
                'invite_member',
                'explain_invoice',
                'register_site',
            ],
            AssistantContextRole::PROVIDER_INDEPENDENT => [
                'update_availability',
                'report_incident',
            ],
            AssistantContextRole::PROVIDER_COMPANY => [
                'report_incident',
                'create_task',
            ],
            AssistantContextRole::ADMIN => [
                'create_booking',
                'assign_mission',
                'invite_member',
                'create_task',
                'explain_invoice',
            ],
        };
    }

    // ──────────────────────────────────────────────────────
    // Prompt de base commun
    // ──────────────────────────────────────────────────────

    private function basePrompt(User $user): string
    {
        return "Tu es l'assistant CleanUx, une plateforme professionnelle de services de nettoyage en Belgique.
Tu réponds toujours en " . ($user->locale === 'nl_BE' ? 'néerlandais' : 'français') . ".
Tu es utile, concis et professionnel. Tu n'inventes jamais de données — si tu ne sais pas, tu le dis.
Date actuelle : " . now()->format('d/m/Y H:i') . ".

";
    }

    // ──────────────────────────────────────────────────────
    // Helpers de formatage
    // ──────────────────────────────────────────────────────

    private function nextBookingLabel(User $user): string
    {
        // À implémenter avec votre modèle Booking
        return 'Aucune planifiée';
    }

    private function activeMissionLabel(User $user): string
    {
        return 'Aucune en cours';
    }

    private function todayMissionsLabel(User $user): string
    {
        return 'Aucune mission aujourd\'hui';
    }

    private function nextMissionLabel(User $user): string
    {
        return 'Aucune';
    }
}
