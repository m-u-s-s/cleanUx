<?php

namespace App\Livewire\ProviderCompany;

use App\Models\Channel;
use App\Models\Message;
use App\Models\Mission;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * @property-read array<string, mixed> $kpis
 * @property-read array<int, array<string, mixed>> $alerts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Mission> $missionsOfDay
 * @property-read Collection<int, array<string, mixed>> $teamStatus
 */
class ProviderDashboard extends Component
{
    use EnforcesActiveOrgMembership;

    public string $period = 'today';

    /**
     * LE TABLEAU DE BORD MONTRE-T-IL LA SOCIÉTÉ, OU SEULEMENT MON TRAVAIL ?
     *
     * Sa seule garde était `isProviderCompanyWorker()` — un test de TYPE DE COMPTE, que tout
     * employé franchit par construction, puisque `OrganizationMembershipService` crée précisément
     * un `ProviderProfile` de type `company_worker` pour chaque membre. Un nettoyeur voyait donc
     * les missions de toute la société, ses retards, et combien de ses collègues n'avaient pas
     * configuré Stripe.
     *
     * `missions.view_all` existait déjà dans la matrice pour dire exactement cela — et rien ne la
     * consultait. Elle est accordée par défaut au propriétaire, au directeur d'opérations, au
     * dispatcheur, au chef d'équipe et au responsable qualité ; refusée au nettoyeur et au lecteur.
     * Une société qui veut ouvrir la vue à ses exécutants le décide chez elle, via
     * `organization_role_permissions`, sans déploiement.
     *
     * Propriété PUBLIQUE et non calculée : la vue en a besoin pour ne pas annoncer « 0 mission
     * aujourd'hui » à qui n'a simplement pas le droit de compter.
     */
    public bool $peutToutVoir = false;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->isProviderCompanyWorker(), 403);

        $this->peutToutVoir = app(PermissionService::class)
            ->can($user, 'missions.view_all', $user->currentOrganization);
    }

    /**
     * Les missions que l'appelant a le droit de compter.
     *
     * Sans `missions.view_all`, on se limite à CE QU'IL FAIT : les missions dont il est le
     * travailleur désigné. Rendre une liste vide serait plus simple et plus faux — son propre
     * travail le regarde.
     *
     * @return Builder<Mission>
     */
    private function missionsVisibles(): Builder
    {
        $user = Auth::user();

        $requete = Mission::where('provider_organization_id', $user->current_organization_id);

        if (! $this->peutToutVoir) {
            $requete->where('lead_provider_user_id', $user->id);
        }

        return $requete;
    }

    public function getKpisProperty(): array
    {
        $user = Auth::user();
        $orgId = $user->current_organization_id;

        [$from, $to] = $this->periodDates();

        $base = fn () => $this->missionsVisibles();

        return [
            'missions_today' => $base()->whereDate('planned_start_at', today())->count(),
            'missions_active' => $base()->whereIn('status', ['dispatched', 'in_progress'])->count(),
            'missions_done' => $base()->where('status', 'completed')->whereBetween('actual_end_at', [$from, $to])->count(),
            'missions_delayed' => $base()->where('status', '!=', 'completed')->where('planned_start_at', '<', now())->count(),
            'members_active' => OrganizationMember::where('organization_account_id', $orgId)->where('status', 'active')->count(),
            /*
             * CE COMPTEUR VALAIT `0` EN DUR, avec pour seul commentaire « calculé via Channel si
             * Reverb actif ». Un zéro se lit comme un fait : le gérant en concluait que personne ne
             * lui écrivait, alors que `TeamChannels` savait déjà compter — la requête existait,
             * dix lignes plus loin dans un autre fichier.
             *
             * Trois conditions, et chacune compte : les canaux de MON organisation, ceux dont je
             * suis MEMBRE (un canal privé auquel je n'appartiens pas ne m'est pas « non lu », il ne
             * me regarde pas), et les messages que je n'ai pas écrits — se compter soi-même
             * afficherait un badge à chaque fois qu'on parle.
             */
            'unread_messages' => Message::query()
                ->whereHas('channel', fn ($q) => $q
                    ->where('organization_account_id', $orgId)
                    ->whereHas('members', fn ($m) => $m->where('user_id', $user->id))
                )
                ->where('user_id', '!=', $user->id)
                ->whereDoesntHave('readBy', fn ($r) => $r->where('user_id', $user->id))
                ->count(),
            'pending_tasks' => Task::forOrg($orgId)->todo()->count(),
        ];
    }

    public function getAlertsProperty(): array
    {
        $orgId = Auth::user()->current_organization_id;
        $alerts = [];

        /*
         * Les alertes sont des alertes de PILOTAGE : « 3 missions en retard », « 2 membres sans
         * Stripe Connect ». La seconde en particulier dit quelque chose de la situation
         * administrative de collègues nommés. Un exécutant n'a rien à en faire, et beaucoup à en
         * déduire.
         */
        if (! $this->peutToutVoir) {
            return [];
        }

        $delayed = Mission::where('provider_organization_id', $orgId)
            ->where('status', '!=', 'completed')
            ->where('planned_start_at', '<', now()->subMinutes(30))
            ->count();

        if ($delayed > 0) {
            $alerts[] = ['level' => 'red', 'icon' => '🚨',
                'message' => "{$delayed} mission(s) en retard de +30 min",
                'route' => 'provider-company.dispatch'];
        }

        $noStripe = OrganizationMember::where('organization_account_id', $orgId)
            ->where('status', 'active')
            ->whereHas('user.providerProfile', fn ($q) => $q->where('stripe_connect_status', '!=', 'active')
            )->count();

        if ($noStripe > 0) {
            $alerts[] = ['level' => 'orange', 'icon' => '💳',
                'message' => "{$noStripe} membre(s) sans Stripe Connect",
                'route' => 'provider-company.team'];
        }

        return $alerts;
    }

    public function getMissionsOfDayProperty()
    {
        return $this->missionsVisibles()
            ->whereDate('planned_start_at', today())
            /*
             * `assignedWorker` N'EXISTE PAS sur Mission (corrigé le 2026-08-05) : le modèle
             * expose `leadProvider()` — le travailleur assigné, via `lead_provider_user_id` — et
             * `assignments()`. Le charger levait `RelationNotFoundException` à chaque rendu
             * comportant une mission du jour : page blanche pour toute société en activité.
             *
             * Un seul nom pour une seule chose : on lit désormais `leadProvider`.
             */
            ->with(['leadProvider:id,name,profile_photo_path'])
            ->orderBy('planned_start_at')
            ->limit(10)
            ->get();
    }

    public function getTeamStatusProperty()
    {
        return OrganizationMember::where('organization_account_id', Auth::user()->current_organization_id)
            ->where('status', 'active')
            ->with(['user:id,name,profile_photo_path', 'user.providerProfile'])
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->user->name,
                'avatar' => $m->user->profile_photo_url,
                'role' => $m->roleLabel(),
                'status' => 'available', // À enrichir avec GPS
            ]);
    }

    private function periodDates(): array
    {
        return match ($this->period) {
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [today()->startOfDay(), today()->endOfDay()],
        };
    }

    public function render()
    {
        return view('livewire.provider-company.provider-dashboard', [
            'kpis' => $this->kpis,
            'alerts' => $this->alerts,
            'missionsDay' => $this->missionsOfDay,
            'teamStatus' => $this->teamStatus,
        ])->layout('layouts.provider-company');
    }
}
