<?php

namespace App\Livewire\Admin\NotificationPreferences;

use App\Models\NotificationPreference;
use App\Models\NotificationPreferenceAudit;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationPreferencesCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'audits';  // audits | matrix-by-channel
    public string $search = '';
    public string $filterChannel = '';
    public string $filterCategory = '';

    public function render(): View
    {
        $kpis = [
            'users_with_prefs' => NotificationPreference::query()->distinct('user_id')->count('user_id'),
            'opt_outs_total' => NotificationPreference::query()->where('is_allowed', false)->count(),
            'audits_24h' => NotificationPreferenceAudit::query()
                ->where('changed_at', '>=', now()->subDay())->count(),
            'audits_total' => NotificationPreferenceAudit::query()->count(),
        ];

        if ($this->tab === 'audits') {
            $items = NotificationPreferenceAudit::query()
                ->with(['user:id,email,name', 'actor:id,email'])
                ->when($this->filterChannel, fn ($q) => $q->where('channel', $this->filterChannel))
                ->when($this->filterCategory, fn ($q) => $q->where('category', $this->filterCategory))
                ->when($this->search, function ($q) {
                    $term = '%' . $this->search . '%';
                    $q->whereHas('user', fn ($u) => $u->where('email', 'like', $term)->orWhere('name', 'like', $term));
                })
                ->orderByDesc('changed_at')
                ->paginate(25);
        } else {
            $items = NotificationPreference::query()
                ->with('user:id,email,name')
                ->when($this->filterChannel, fn ($q) => $q->where('channel', $this->filterChannel))
                ->when($this->filterCategory, fn ($q) => $q->where('category', $this->filterCategory))
                ->when($this->search, function ($q) {
                    $term = '%' . $this->search . '%';
                    $q->whereHas('user', fn ($u) => $u->where('email', 'like', $term)->orWhere('name', 'like', $term));
                })
                ->orderByDesc('last_changed_at')
                ->paginate(25);
        }

        return view('livewire.admin.notification-preferences.notification-preferences-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
