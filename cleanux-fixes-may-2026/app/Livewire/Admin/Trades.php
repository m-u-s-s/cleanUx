<?php

namespace App\Livewire\Admin;

use App\Models\Trade;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
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
    use WithPagination;

    // ── Filtres ──
    public string $search = '';
    public string $status = '';   // ''|'active'|'inactive'

    protected $queryString = ['search', 'status', 'page'];

    // ── Form (create / edit) ──
    public ?int $tradeId = null;
    public string $slug = '';
    public string $code = '';
    public string $name = '';
    public string $icon = '';
    public string $color = '#0EA5E9';
    public string $short_description = '';
    public string $description = '';
    public bool $is_active = true;
    public bool $requires_certification = false;
    public bool $requires_insurance_proof = false;
    public bool $is_b2b_default = true;
    public bool $is_personal_default = true;
    public int $sort_order = 0;

    public bool $showForm = false;

    public function mount(): void
    {
        $this->resetForm();
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }

    // Auto-slug + auto-code à la création seulement
    public function updatedName($value): void
    {
        if (! $this->tradeId) {
            $this->slug = Str::slug((string) $value);
            $this->code = Str::upper(Str::limit(Str::slug((string) $value, '_'), 30, ''));
        }
    }

    public function resetForm(): void
    {
        $this->tradeId = null;
        $this->slug = '';
        $this->code = '';
        $this->name = '';
        $this->icon = '';
        $this->color = '#0EA5E9';
        $this->short_description = '';
        $this->description = '';
        $this->is_active = true;
        $this->requires_certification = false;
        $this->requires_insurance_proof = false;
        $this->is_b2b_default = true;
        $this->is_personal_default = true;
        $this->sort_order = 0;

        $this->resetErrorBag();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $trade = Trade::findOrFail($id);

        $this->tradeId = $trade->id;
        $this->slug = (string) $trade->slug;
        $this->code = (string) $trade->code;
        $this->name = (string) $trade->name;
        $this->icon = (string) ($trade->icon ?? '');
        $this->color = (string) ($trade->color ?? '#0EA5E9');
        $this->short_description = (string) ($trade->short_description ?? '');
        $this->description = (string) ($trade->description ?? '');
        $this->is_active = (bool) $trade->is_active;
        $this->requires_certification = (bool) $trade->requires_certification;
        $this->requires_insurance_proof = (bool) $trade->requires_insurance_proof;
        $this->is_b2b_default = (bool) $trade->is_b2b_default;
        $this->is_personal_default = (bool) $trade->is_personal_default;
        $this->sort_order = (int) $trade->sort_order;

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $rules = [
            'slug'                     => ['required', 'string', 'max:80', 'regex:/^[a-z0-9\-]+$/'],
            'code'                     => ['required', 'string', 'max:60'],
            'name'                     => ['required', 'string', 'max:120'],
            'icon'                     => ['nullable', 'string', 'max:60'],
            'color'                    => ['nullable', 'string', 'max:16'],
            'short_description'        => ['nullable', 'string', 'max:500'],
            'description'              => ['nullable', 'string', 'max:5000'],
            'is_active'                => ['boolean'],
            'requires_certification'   => ['boolean'],
            'requires_insurance_proof' => ['boolean'],
            'is_b2b_default'           => ['boolean'],
            'is_personal_default'      => ['boolean'],
            'sort_order'               => ['integer', 'min:0', 'max:9999'],
        ];

        $validated = $this->validate($rules);

        // Unicité avec exception sur l'enregistrement édité
        $duplicateSlug = Trade::query()
            ->where('slug', $validated['slug'])
            ->when($this->tradeId, fn ($q) => $q->where('id', '!=', $this->tradeId))
            ->exists();
        if ($duplicateSlug) {
            $this->addError('slug', "Ce slug est déjà utilisé.");
            return;
        }

        $duplicateCode = Trade::query()
            ->where('code', $validated['code'])
            ->when($this->tradeId, fn ($q) => $q->where('id', '!=', $this->tradeId))
            ->exists();
        if ($duplicateCode) {
            $this->addError('code', "Ce code est déjà utilisé.");
            return;
        }

        if ($this->tradeId) {
            $trade = Trade::findOrFail($this->tradeId);
            $trade->update($validated);
            ActivityLogger::log('admin.trade.updated', $trade, ['changes' => $validated]);
            session()->flash('success', "Métier « {$trade->name} » mis à jour.");
        } else {
            $trade = Trade::create($validated);
            ActivityLogger::log('admin.trade.created', $trade, ['attributes' => $validated]);
            session()->flash('success', "Métier « {$trade->name} » créé.");
        }

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
                $term = '%' . $this->search . '%';
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
