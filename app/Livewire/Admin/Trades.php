<?php

namespace App\Livewire\Admin;

use App\Models\Trade;
use App\Support\ActivityLogger;
use App\Support\Livewire\Concerns\Admin\ManagesTradeForm;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Phase 1 — Admin Trades.
 *
 * CRUD complet des corps de métier de la plateforme, sur le même pattern
 * monolithique que CatalogueServices (single-component, queryString sync,
 * ActivityLogger sur les mutations).
 *
 * NB layout : utilise `layouts.app` comme toutes les autres pages admin.
 * (la convention historique du repo n'expose PAS de `layouts.admin`).
 */
#[Layout('layouts.app')]
class Trades extends Component
{
    use EnforcesAdminAccess;
    use ManagesTradeForm;
    use WithPagination;

    // ── Filtres ──
    public string $search = '';

    public string $status = '';   // ''|'active'|'inactive'

    protected $queryString = ['search', 'status', 'page'];

    // ── Formulaire ──
    // Les 21 champs, leurs règles et leur persistance vivent dans `ManagesTradeForm`, partagé avec
    // le catalogue. Deux copies auraient divergé au premier champ ajouté, et l'écran oublié aurait
    // continué d'enregistrer des métiers incomplets sans lever d'erreur.

    public bool $showForm = false;

    public function mount(): void
    {
        $this->resetForm();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->resetTradeForm();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->fillTradeForm(Trade::findOrFail($id));
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $creation = $this->tradeId === null;
        $trade = $this->persistTradeForm();

        // `null` signifie « refusé » : les erreurs sont déjà dans le sac, et le formulaire doit
        // rester ouvert avec les vingt champs que l'administrateur venait de remplir.
        if ($trade === null) {
            return;
        }

        session()->flash('success', $creation
            ? "Métier « {$trade->name} » créé."
            : "Métier « {$trade->name} » mis à jour.");

        $this->closeForm();
    }

    public function toggleActive(int $id): void
    {
        $trade = Trade::findOrFail($id);
        $trade->is_active = ! $trade->is_active;
        $trade->save();

        ActivityLogger::log('admin.trade.toggle_active', $trade, [
            'is_active' => $trade->is_active,
        ]);
    }

    public function moveUp(int $id): void
    {
        $trade = Trade::findOrFail($id);
        $previous = Trade::query()
            ->where('sort_order', '<', $trade->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if (! $previous) {
            return;
        }

        [$trade->sort_order, $previous->sort_order] = [$previous->sort_order, $trade->sort_order];
        $trade->save();
        $previous->save();

        ActivityLogger::log('admin.trade.reorder', $trade, ['direction' => 'up']);
    }

    public function moveDown(int $id): void
    {
        $trade = Trade::findOrFail($id);
        $next = Trade::query()
            ->where('sort_order', '>', $trade->sort_order)
            ->orderBy('sort_order')
            ->first();

        if (! $next) {
            return;
        }

        [$trade->sort_order, $next->sort_order] = [$next->sort_order, $trade->sort_order];
        $trade->save();
        $next->save();

        ActivityLogger::log('admin.trade.reorder', $trade, ['direction' => 'down']);
    }

    public function delete(int $id): void
    {
        $trade = Trade::withCount('services')->findOrFail($id);

        if ($trade->services_count > 0) {
            session()->flash(
                'error',
                "Impossible de supprimer « {$trade->name} » : {$trade->services_count} service(s) encore rattaché(s)."
            );

            return;
        }

        $trade->delete(); // soft delete
        ActivityLogger::log('admin.trade.deleted', $trade);
        session()->flash('success', "Métier « {$trade->name} » supprimé.");
    }

    public function render(): View
    {
        $trades = Trade::query()
            ->withCount('services')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('code', 'like', $term);
                });
            })
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.trades', [
            'trades' => $trades,
        ]);
    }
}
