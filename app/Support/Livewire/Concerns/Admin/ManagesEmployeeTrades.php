<?php

namespace App\Support\Livewire\Concerns\Admin;

use App\Models\Trade;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Gate;

/**
 * Gère l'assignation des métiers (Trade) à un employé ou prestataire.
 *
 * Doit être branché dans un composant Livewire admin. Expose :
 *   - openEmployeeTrades(int $userId)
 *   - cancelEmployeeTrades()
 *   - toggleEmployeeTrade(int $tradeId)
 *   - setEmployeeTradePrimary(int $tradeId)
 *   - saveEmployeeTrades()
 *
 * Sync via belongsToMany avec pivot is_primary/proficiency/notes.
 */
trait ManagesEmployeeTrades
{
    /** ID de l'utilisateur dont on édite les métiers (null = modal fermée). */
    public ?int $editingTradesUserId = null;

    /** Sélection en cours [trade_id => ['proficiency'=>'...', 'notes'=>'...']]. */
    public array $employeeTradesSelection = [];

    /** ID du métier principal (un seul à la fois). */
    public ?int $employeeTradesPrimary = null;

    protected function authorizeEmployeeTradeManagement(): void
    {
        Gate::authorize('manage-services');
        Gate::authorize('perform-critical-admin-actions');
    }

    public function openEmployeeTrades(int $userId): void
    {
        $user = User::with('trades')->findOrFail($userId);
        $this->authorizeEmployeeTradeManagement();

        $this->editingTradesUserId = $user->id;
        $this->employeeTradesPrimary = null;

        $this->employeeTradesSelection = $user->trades
            ->mapWithKeys(function (Trade $trade) {
                $isPrimary = (bool) ($trade->pivot->is_primary ?? false);
                if ($isPrimary) {
                    $this->employeeTradesPrimary = $trade->id;
                }
                return [
                    $trade->id => [
                        'selected'    => true,
                        'proficiency' => (string) ($trade->pivot->proficiency ?? ''),
                        'notes'       => (string) ($trade->pivot->notes ?? ''),
                    ],
                ];
            })
            ->toArray();
    }

    public function cancelEmployeeTrades(): void
    {
        $this->editingTradesUserId = null;
        $this->employeeTradesSelection = [];
        $this->employeeTradesPrimary = null;
        $this->resetErrorBag();
    }

    public function toggleEmployeeTrade(int $tradeId): void
    {
        $entry = $this->employeeTradesSelection[$tradeId] ?? [
            'selected'    => false,
            'proficiency' => '',
            'notes'       => '',
        ];
        $entry['selected'] = ! (bool) ($entry['selected'] ?? false);
        $this->employeeTradesSelection[$tradeId] = $entry;

        // Si on désactive le métier principal, on perd la primauté
        if (! $entry['selected'] && $this->employeeTradesPrimary === $tradeId) {
            $this->employeeTradesPrimary = null;
        }
    }

    public function setEmployeeTradePrimary(?int $tradeId): void
    {
        if ($tradeId === null) {
            $this->employeeTradesPrimary = null;
            return;
        }

        // Le primaire doit être sélectionné
        $entry = $this->employeeTradesSelection[$tradeId] ?? null;
        if (! $entry || ! ($entry['selected'] ?? false)) {
            $this->employeeTradesSelection[$tradeId] = array_merge(
                $entry ?: [],
                ['selected' => true, 'proficiency' => $entry['proficiency'] ?? '', 'notes' => $entry['notes'] ?? '']
            );
        }
        $this->employeeTradesPrimary = $tradeId;
    }

    public function saveEmployeeTrades(): void
    {
        $this->authorizeEmployeeTradeManagement();
        abort_unless($this->editingTradesUserId !== null, 422);

        $this->validate([
            'editingTradesUserId'                  => ['required', 'exists:users,id'],
            'employeeTradesSelection'              => ['array'],
            'employeeTradesSelection.*.selected'   => ['boolean'],
            'employeeTradesSelection.*.proficiency'=> ['nullable', 'in:basic,standard,expert,'],
            'employeeTradesSelection.*.notes'      => ['nullable', 'string', 'max:1000'],
            'employeeTradesPrimary'                => ['nullable', 'integer', 'exists:trades,id'],
        ]);

        $user = User::findOrFail($this->editingTradesUserId);

        $sync = collect($this->employeeTradesSelection)
            ->filter(fn ($entry, $tradeId) => (bool) ($entry['selected'] ?? false))
            ->mapWithKeys(function ($entry, $tradeId) {
                $tradeId = (int) $tradeId;
                return [$tradeId => [
                    'is_primary'  => $tradeId === $this->employeeTradesPrimary,
                    'proficiency' => filled($entry['proficiency'] ?? null) ? $entry['proficiency'] : null,
                    'notes'       => filled($entry['notes'] ?? null) ? $entry['notes'] : null,
                    'created_by'  => auth()->id(),
                ]];
            })
            ->toArray();

        // Si le primaire choisi n'est plus dans la sélection, on l'invalide
        if ($this->employeeTradesPrimary && ! isset($sync[$this->employeeTradesPrimary])) {
            $this->employeeTradesPrimary = null;
        }

        $user->trades()->sync($sync);

        ActivityLogger::critical('admin.user.trades_assigned', $user, [
            'domain'     => 'security',
            'trade_ids'  => array_keys($sync),
            'primary_id' => $this->employeeTradesPrimary,
        ]);

        session()->flash('success', 'Métiers de l\'utilisateur mis à jour ('.count($sync).').');
        $this->cancelEmployeeTrades();
    }

    public function getAllAvailableTradesProperty()
    {
        return Trade::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
