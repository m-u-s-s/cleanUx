<?php

namespace App\Livewire\ProviderCompany;

use App\Models\Channel;
use App\Models\Mission;
use App\Models\OrganizationMember;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProviderDashboard extends Component
{
    public string $period = 'today';

    public function mount(): void
    {
        abort_unless(Auth::user()->isProviderCompanyWorker(), 403);
    }

    public function getKpisProperty(): array
    {
        $user  = Auth::user();
        $orgId = $user->current_organization_id;

        [$from, $to] = $this->periodDates();

        $base = fn () => Mission::where('provider_organization_id', $orgId);

        return [
            'missions_today'    => $base()->whereDate('scheduled_at', today())->count(),
            'missions_active'   => $base()->whereIn('status', ['dispatched', 'in_progress'])->count(),
            'missions_done'     => $base()->where('status', 'completed')->whereBetween('completed_at', [$from, $to])->count(),
            'missions_delayed'  => $base()->where('status', '!=', 'completed')->where('scheduled_at', '<', now())->count(),
            'members_active'    => OrganizationMember::where('organization_account_id', $orgId)->where('status', 'active')->count(),
            'unread_messages'   => 0, // calculé via Channel si Reverb actif
            'pending_tasks'     => Task::forOrg($orgId)->todo()->count(),
        ];
    }

    public function getAlertsProperty(): array
    {
        $orgId  = Auth::user()->current_organization_id;
        $alerts = [];

        $delayed = Mission::where('provider_organization_id', $orgId)
            ->where('status', '!=', 'completed')
            ->where('scheduled_at', '<', now()->subMinutes(30))
            ->count();

        if ($delayed > 0) {
            $alerts[] = ['level' => 'red', 'icon' => '🚨',
                'message' => "{$delayed} mission(s) en retard de +30 min",
                'route'   => 'provider-company.dispatch'];
        }

        $noStripe = OrganizationMember::where('organization_account_id', $orgId)
            ->where('status', 'active')
            ->whereHas('user.providerProfile', fn ($q) =>
                $q->where('stripe_connect_status', '!=', 'active')
            )->count();

        if ($noStripe > 0) {
            $alerts[] = ['level' => 'orange', 'icon' => '💳',
                'message' => "{$noStripe} membre(s) sans Stripe Connect",
                'route'   => 'provider-company.team'];
        }

        return $alerts;
    }

    public function getMissionsOfDayProperty()
    {
        return Mission::where('provider_organization_id', Auth::user()->current_organization_id)
            ->whereDate('scheduled_at', today())
            ->with(['assignedWorker:id,name,profile_photo_path'])
            ->orderBy('scheduled_at')
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
                'id'     => $m->id,
                'name'   => $m->user->name,
                'avatar' => $m->user->profile_photo_url,
                'role'   => $m->roleLabel(),
                'status' => 'available', // À enrichir avec GPS
            ]);
    }

    private function periodDates(): array
    {
        return match ($this->period) {
            'week'  => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [today()->startOfDay(), today()->endOfDay()],
        };
    }

    public function render()
    {
        return view('livewire.provider-company.provider-dashboard', [
            'kpis'        => $this->kpisProperty,
            'alerts'      => $this->alertsProperty,
            'missionsDay' => $this->missionsOfDayProperty,
            'teamStatus'  => $this->teamStatusProperty,
        ])->layout('layouts.provider-company');
    }
}
