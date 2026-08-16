<?php

namespace App\Livewire\Admin\Availability;

use App\Models\AvailabilityException;
use App\Models\AvailabilityHold;
use App\Models\AvailabilitySlot;
use App\Models\User;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * LE CENTRE DES DISPONIBILITÉS — et surtout ceux qui n'en ont pas.
 *
 * Cette page listait `User::whereHas('availabilitySlots')` : elle ne montrait QUE les prestataires
 * qui ont déjà des créneaux. Le compte à zéro disponibilité — précisément celui qu'une
 * administration doit repérer, puisqu'il est injoignable à la planification sans le savoir —
 * était structurellement absent de la liste. Elle affichait un indicateur « prestataires
 * configurés » sans jamais pouvoir dire qui manquait à l'appel.
 *
 * La liste porte maintenant TOUS les prestataires, avec un filtre pour isoler les comptes muets,
 * et chaque nom ouvre sa fiche.
 */
class AvailabilityCenter extends Component
{
    use EnforcesAdminAccess;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $search = '';

    /** `tous` | `sans_creneau` | `configures` */
    public string $filtre = 'tous';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFiltre(): void
    {
        $this->resetPage();
    }

    /**
     * Qui est prestataire — la même définition que `DefaultAvailabilityProvisioner`.
     *
     * `whereHas('providerProfile')` seul écarterait les comptes dont seule la colonne héritée
     * `role` porte l'information. Deux définitions du même mot dans la même application, c'est le
     * défaut qu'on a déjà rencontré sur la commande de rattrapage.
     *
     * @param  Builder<User>  $query
     */
    protected function scopePrestataires(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->whereHas('providerProfile')->orWhere('role', 'employe');
        });
    }

    public function render(): View
    {
        $totalPrestataires = User::query()->tap(fn ($q) => $this->scopePrestataires($q))->count();

        $sansCreneau = User::query()
            ->tap(fn ($q) => $this->scopePrestataires($q))
            ->whereDoesntHave('availabilitySlots')
            ->count();

        $kpis = [
            'active_slots' => AvailabilitySlot::query()->where('is_active', true)->count(),
            'providers_total' => $totalPrestataires,
            'providers_without_slots' => $sansCreneau,
            'exceptions_30d' => AvailabilityException::query()
                ->where('date', '>=', now()->subDays(30))->count(),
            'active_holds' => AvailabilityHold::query()->active()->count(),
        ];

        $providers = User::query()
            ->tap(fn ($q) => $this->scopePrestataires($q))
            ->when($this->filtre === 'sans_creneau', fn ($q) => $q->whereDoesntHave('availabilitySlots'))
            ->when($this->filtre === 'configures', fn ($q) => $q->whereHas('availabilitySlots'))
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
            })
            ->withCount([
                'availabilitySlots as slots_count' => fn ($q) => $q->where('is_active', true),
                /*
                 * Le compte d'exceptions était calculé DANS la boucle Blade : une requête par
                 * ligne, vingt par page. Il rejoint les autres agrégats, en une seule.
                 */
                'availabilityExceptions as exceptions_count' => fn ($q) => $q->where('date', '>=', now()->subDays(30)),
            ])
            // Les comptes muets d'abord : c'est pour eux qu'on ouvre cette page.
            ->orderBy('slots_count')
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.admin.availability.availability-center', [
            'kpis' => $kpis,
            'providers' => $providers,
        ]);
    }
}
