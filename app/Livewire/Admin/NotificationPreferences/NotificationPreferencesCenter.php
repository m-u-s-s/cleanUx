<?php

namespace App\Livewire\Admin\NotificationPreferences;

use App\Models\NotificationPreference;
use App\Models\NotificationPreferenceAudit;
use App\Services\NotificationPreferences\NotificationPreferenceService;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationPreferencesCenter extends Component
{
    use EnforcesAdminAccess;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'audits';  // audits | matrix-by-channel

    public string $search = '';

    public string $filterChannel = '';

    public string $filterCategory = '';

    /**
     * L'ECRAN NE SAVAIT QUE REGARDER — une seule methode, `render`.
     *
     * Un centre de preferences qui affiche les opt-out sans pouvoir en corriger un oblige a
     * passer par la base. On ne reecrit pas la regle ici : `NotificationPreferenceService`
     * refuse deja de couper une categorie obligatoire et ecrit le journal RGPD versionne.
     * La source est `admin` et l'acteur est nomme : une correction faite POUR quelqu'un ne doit
     * pas se confondre avec un choix fait PAR lui.
     */
    public function basculerLaPreference(int $preferenceId): void
    {
        abort_unless(Gate::allows('manage-compliance'), 403);

        $preference = NotificationPreference::query()->with('user')->findOrFail($preferenceId);

        if (! $preference->user) {
            $this->dispatch('toast', 'Préférence orpheline : son porteur n’existe plus.', 'error');

            return;
        }

        $avant = (bool) $preference->is_allowed;

        $apres = app(NotificationPreferenceService::class)->setPreference(
            user: $preference->user,
            channel: (string) $preference->channel,
            category: (string) $preference->category,
            isAllowed: ! $avant,
            source: NotificationPreference::SOURCE_ADMIN,
            request: request(),
            actor: Auth::user(),
        );

        // Une categorie obligatoire se refuse EN SILENCE cote service : on le dit.
        if ((bool) $apres->is_allowed === $avant) {
            $this->dispatch('toast', 'Catégorie obligatoire : elle ne peut pas être coupée.', 'error');

            return;
        }

        $this->dispatch('toast', $apres->is_allowed ? 'Préférence rouverte.' : 'Préférence coupée.', 'success');
    }

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
                    $term = '%'.$this->search.'%';
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
                    $term = '%'.$this->search.'%';
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
