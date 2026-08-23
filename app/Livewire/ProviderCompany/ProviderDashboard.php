<?php

namespace App\Livewire\ProviderCompany;

use App\Models\Channel;
use App\Models\Message;
use App\Models\Mission;
use App\Models\OrganizationMember;
use App\Services\PermissionService;
use App\Services\Tasks\TaskVisibilityService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
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

    /** LE TABLEAU DE BORD MONTRE-T-IL LA SOCIÉTÉ, OU SEULEMENT MON TRAVAIL ? */
    #[Locked]
    public bool $peutToutVoir = false;

    /** L'effectif et le trombinoscope relèvent de `team.view`, pas de `missions.view_all`. */
    #[Locked]
    public bool $peutVoirLEquipe = false;

    /** Pour les raccourcis du bas de page : un lien vers un écran qu'on ne peut pas ouvrir est un 403 déguisé en invitation. */
    #[Locked]
    public bool $peutRepartir = false;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->isProviderCompanyWorker(), 403);

        $permissions = app(PermissionService::class);
        $organisation = $user->currentOrganization;

        $this->peutToutVoir = $permissions->can($user, 'missions.view_all', $organisation);
        $this->peutVoirLEquipe = $permissions->can($user, 'team.view', $organisation);
        $this->peutRepartir = $permissions->can($user, 'missions.dispatch', $organisation);
    }

    /**
     * Les missions que l'appelant a le droit de compter.
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
            // `null` PLUTÔT QUE `0` : la vue retire la carte au lieu d'afficher un chiffre faux.
            'members_active' => $this->peutVoirLEquipe
                ? OrganizationMember::where('organization_account_id', $orgId)->where('status', 'active')->count()
                : null,
            // CE COMPTEUR VALAIT `0` EN DUR, avec pour seul commentaire « calculé via Channel si Reverb actif ».
            'unread_messages' => Message::query()
                ->whereHas('channel', fn ($q) => $q
                    ->where('organization_account_id', $orgId)
                    ->whereHas('members', fn ($m) => $m->where('user_id', $user->id))
                )
                ->where('user_id', '!=', $user->id)
                ->whereDoesntHave('readBy', fn ($r) => $r->where('user_id', $user->id))
                ->count(),
            // LE COMPTEUR SUIT LE TABLEAU.
            'pending_tasks' => app(TaskVisibilityService::class)
                ->requetePour($user, $orgId)
                ->todo()
                ->count(),
        ];
    }

    public function getAlertsProperty(): array
    {
        $orgId = Auth::user()->current_organization_id;
        $alerts = [];

        // Les alertes sont des alertes de PILOTAGE : « 3 missions en retard », « 2 membres sans Stripe Connect ».
        if (! $this->peutToutVoir) {
            return [];
        }

        $delayed = Mission::where('provider_organization_id', $orgId)
            ->where('status', '!=', 'completed')
            ->where('planned_start_at', '<', now()->subMinutes(30))
            ->count();

        // L'ALERTE RESTE, SON LIEN PEUT DISPARAÎTRE.
        if ($delayed > 0) {
            $alerts[] = ['level' => 'red', 'icon' => '🚨',
                'message' => "{$delayed} mission(s) en retard de +30 min",
                'route' => $this->peutRepartir ? 'provider-company.dispatch' : null];
        }

        // La situation Stripe de collègues nommés est une information d'ÉQUIPE : elle suit `team.view`, comme le trombinoscope, et non le droit de compter les missions.
        if (! $this->peutVoirLEquipe) {
            return $alerts;
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
            // `assignedWorker` N'EXISTE PAS sur Mission (corrigé le 2026-08-05) : le modèle expose `leadProvider()` — le travailleur assigné, via `lead_provider_user_id` — et `assignments()`.
            ->with(['leadProvider:id,name,profile_photo_path'])
            ->orderBy('planned_start_at')
            ->limit(10)
            ->get();
    }

    /** QUI TRAVAILLE ICI, ET DANS QUEL ÉTAT — un trombinoscope, pas un indicateur. */
    public function getTeamStatusProperty()
    {
        if (! $this->peutVoirLEquipe) {
            return collect();
        }

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
