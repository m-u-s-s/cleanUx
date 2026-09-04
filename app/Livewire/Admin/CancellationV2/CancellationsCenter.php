<?php

namespace App\Livewire\Admin\CancellationV2;

use App\Models\BookingCancellationV2;
use App\Models\CancellationPolicy;
use App\Services\CancellationV2\CancellationEngine;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class CancellationsCenter extends Component
{
    use EnforcesAdminAccess;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'recent';

    public string $filterActorRole = '';

    public string $search = '';

    /**
     * LES ONGLETS ET LA CAPACITE QUI LES OUVRE.
     *
     * La page reunit des ecrans de finance et un pivot d'analyse : sans capacite par onglet, un
     * analyste entre par la porte commune et se retrouve devant le bouton qui annule des frais.
     *
     * @var array<string, array{libelle: string, capacite: string}>
     */
    public const ONGLETS = [
        'recent' => ['libelle' => 'Récentes', 'capacite' => 'manage-finance'],
        'overrides' => ['libelle' => 'Overrides', 'capacite' => 'manage-finance'],
        'policies' => ['libelle' => 'Politiques', 'capacite' => 'manage-finance'],
        'questionnaire' => ['libelle' => 'Questionnaire', 'capacite' => 'manage-finance'],
        'raisons' => ['libelle' => 'Raisons', 'capacite' => 'manage-analytics'],
    ];

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'tab' => ['except' => 'recent'],
        'filterActorRole' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->updatedTab();
    }

    /** Un onglet interdit ou invente retombe sur le premier que l'on peut ouvrir. */
    public function updatedTab(): void
    {
        if (! $this->peutOuvrir($this->tab)) {
            $this->tab = collect(self::ONGLETS)
                ->keys()
                ->first(fn (string $cle) => $this->peutOuvrir($cle)) ?? 'raisons';
        }

        $this->resetPage();
    }

    /** @return list<string> */
    public function ongletsOuverts(): array
    {
        return array_values(array_filter(array_keys(self::ONGLETS), fn (string $cle) => $this->peutOuvrir($cle)));
    }

    private function peutOuvrir(string $onglet): bool
    {
        $capacite = self::ONGLETS[$onglet]['capacite'] ?? null;

        return $capacite !== null && Gate::allows($capacite);
    }

    public function override(int $cancellationId, string $reason = ''): void
    {
        // RENONCER A DES FRAIS EST UN ACTE DE FINANCE. Cette methode est joignable par
        // `/livewire/update` sans qu'aucun bouton existe : la garde vit ici, pas dans la vue.
        abort_unless(Gate::allows('manage-finance'), 403);

        $row = BookingCancellationV2::findOrFail($cancellationId);
        try {
            app(CancellationEngine::class)->override($row, Auth::user(), $reason ?: 'Override via admin UI: '.now()->toIso8601String());
            $this->dispatch('toast', 'Cancellation overridden — fee waived.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : '.$e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $kpis = [
            'cancellations_7d' => BookingCancellationV2::query()
                ->where('cancelled_at', '>=', now()->subDays(7))->count(),
            'fees_collected_7d_cents' => (int) BookingCancellationV2::query()
                ->where('cancelled_at', '>=', now()->subDays(7))
                ->sum('fee_amount_cents'),
            'overrides_7d' => BookingCancellationV2::query()
                ->whereNotNull('override_admin_user_id')
                ->where('cancelled_at', '>=', now()->subDays(7))->count(),
            'active_policies' => CancellationPolicy::query()->active()->count(),
        ];

        // Les deux onglets rapportes rendent leur propre composant : rien a paginer ici.
        if (in_array($this->tab, ['questionnaire', 'raisons'], true)) {
            return view('livewire.admin.cancellation-v2.cancellations-center', [
                'kpis' => $kpis,
                'items' => null,
            ]);
        }

        if ($this->tab === 'recent') {
            $items = BookingCancellationV2::query()
                ->with(['policy:id,code,name', 'cancelledBy:id,email,name'])
                ->when($this->filterActorRole, fn ($q) => $q->where('actor_role', $this->filterActorRole))
                ->when($this->search, function ($q) {
                    $term = '%'.$this->search.'%';
                    $q->whereHas('cancelledBy', fn ($u) => $u->where('email', 'like', $term));
                })
                ->orderByDesc('cancelled_at')
                ->paginate(25);
        } elseif ($this->tab === 'overrides') {
            $items = BookingCancellationV2::query()
                ->with(['policy:id,code', 'overriddenBy:id,email'])
                ->whereNotNull('override_admin_user_id')
                ->orderByDesc('cancelled_at')
                ->paginate(25);
        } else {
            $items = CancellationPolicy::query()
                ->withCount('tiers')
                ->orderBy('code')
                ->paginate(25);
        }

        return view('livewire.admin.cancellation-v2.cancellations-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
