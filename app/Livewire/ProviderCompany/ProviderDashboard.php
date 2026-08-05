<?php

namespace App\Livewire\ProviderCompany;

use App\Models\Channel;
use App\Models\Mission;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
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

    public function mount(): void
    {
        abort_unless(Auth::user()->isProviderCompanyWorker(), 403);
    }

    public function getKpisProperty(): array
    {
        $user = Auth::user();
        $orgId = $user->current_organization_id;

        [$from, $to] = $this->periodDates();

        $base = fn () => Mission::where('provider_organization_id', $orgId);

        return [
            'missions_today' => $base()->whereDate('planned_start_at', today())->count(),
            'missions_active' => $base()->whereIn('status', ['dispatched', 'in_progress'])->count(),
            'missions_done' => $base()->where('status', 'completed')->whereBetween('actual_end_at', [$from, $to])->count(),
            'missions_delayed' => $base()->where('status', '!=', 'completed')->where('planned_start_at', '<', now())->count(),
            'members_active' => OrganizationMember::where('organization_account_id', $orgId)->where('status', 'active')->count(),
            'unread_messages' => 0, // calculé via Channel si Reverb actif
            'pending_tasks' => Task::forOrg($orgId)->todo()->count(),
        ];
    }

    public function getAlertsProperty(): array
    {
        $orgId = Auth::user()->current_organization_id;
        $alerts = [];

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
        return Mission::where('provider_organization_id', Auth::user()->current_organization_id)
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
