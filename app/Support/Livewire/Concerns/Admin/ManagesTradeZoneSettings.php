<?php

namespace App\Support\Livewire\Concerns\Admin;

use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Support\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/** L'activation et la tarification d'un métier DANS UNE ZONE — sur la ligne qui fait foi. */
trait ManagesTradeZoneSettings
{
    public array $tradeSettings = [];

    protected function loadTradeSettingsForZone(?int $zoneId): void
    {
        if (! $zoneId) {
            $this->tradeSettings = [];

            return;
        }

        $existing = TradeZonePricing::query()
            ->where('service_zone_id', $zoneId)
            ->get()
            ->keyBy('trade_id');

        $this->tradeSettings = Trade::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (Trade $trade) use ($existing) {
                $ligne = $existing->get($trade->id);

                return [
                    $trade->id => [
                        'trade_name' => (string) $trade->name,
                        'trade_slug' => (string) $trade->slug,
                        'trade_color' => (string) ($trade->color ?: '#64748b'),
                        'trade_icon' => (string) ($trade->icon ?: 'briefcase'),
                        'is_active' => $ligne !== null && (bool) $ligne->is_active,
                        'price_multiplier' => $ligne?->surge_multiplier !== null
                            ? (string) $ligne->surge_multiplier
                            : '1.00',
                        // L'immédiat se décide ici aussi : c'est la même ligne, et le séparer
                        // reproduirait exactement le doublon qu'on vient de supprimer.
                        'asap_enabled' => $ligne !== null && (bool) $ligne->asap_enabled,
                        'allows_asap' => (bool) $trade->allows_asap,
                        'notes' => (string) (($ligne?->metadata['notes'] ?? null) ?? ''),
                    ],
                ];
            })
            ->toArray();
    }

    protected function authorizeTradeZoneManagement(): void
    {
        Gate::authorize('manage-services');
        Gate::authorize('perform-critical-admin-actions');
    }

    protected function persistTradeSetting(ServiceZone $zone, int $tradeId, array $payload): TradeZonePricing
    {
        $ligne = TradeZonePricing::query()->firstOrNew([
            'trade_id' => $tradeId,
            'service_zone_id' => $zone->id,
        ]);

        if (! $ligne->exists) {
            // Première ouverture : on part du tarif du métier. Une ligne à zéro euro ferait
            // travailler la plateforme gratuitement jusqu'à ce que quelqu'un s'en aperçoive.
            $ligne->base_rate_cents = (int) (Trade::query()->whereKey($tradeId)->value('base_price_cents') ?? 0);
        }

        $ligne->is_active = (bool) ($payload['is_active'] ?? true);

        // La colonne est un décimal : y écrire un float PHP la ferait arrondir au hasard de la
        // conversion. On écrit la chaîne que la base attend.
        $ligne->surge_multiplier = filled($payload['price_multiplier'] ?? null)
            ? number_format((float) $payload['price_multiplier'], 2, '.', '')
            : '1.00';

        if (array_key_exists('asap_enabled', $payload)) {
            $ligne->asap_enabled = (bool) $payload['asap_enabled'];
        }

        $ligne->metadata = array_merge($ligne->metadata ?? [], [
            'notes' => filled($payload['notes'] ?? null) ? $payload['notes'] : null,
            'updated_by' => auth()->id(),
        ]);

        $ligne->save();

        return $ligne;
    }

    public function saveTradeSetting(int $tradeId): void
    {
        $this->authorizeTradeZoneManagement();

        $this->validate([
            'selectedZoneId' => ['required', 'exists:service_zones,id'],
            "tradeSettings.$tradeId.is_active" => ['boolean'],
            "tradeSettings.$tradeId.price_multiplier" => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            "tradeSettings.$tradeId.notes" => ['nullable', 'string', 'max:1000'],
        ]);

        $zone = ServiceZone::findOrFail($this->selectedZoneId);
        Trade::findOrFail($tradeId); // valide que le trade existe

        $payload = $this->tradeSettings[$tradeId] ?? [];
        $ligne = $this->persistTradeSetting($zone, $tradeId, $payload);

        ActivityLogger::log('zone_trade_setting.updated', $zone, [
            'trade_id' => $tradeId,
            'setting_id' => $ligne->id,
            'payload' => Arr::only($payload, ['is_active', 'price_multiplier', 'notes', 'asap_enabled']),
        ]);

        session()->flash('success', 'Métier mis à jour pour cette zone.');
        $this->selectZone($zone->id);
    }

    public function saveAllTradeSettings(): void
    {
        $this->authorizeTradeZoneManagement();

        $this->validate([
            'selectedZoneId' => ['required', 'exists:service_zones,id'],
            'tradeSettings.*.is_active' => ['boolean'],
            'tradeSettings.*.price_multiplier' => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            'tradeSettings.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $zone = ServiceZone::findOrFail($this->selectedZoneId);
        foreach ($this->tradeSettings as $tradeId => $payload) {
            $this->persistTradeSetting($zone, (int) $tradeId, $payload);
        }

        ActivityLogger::log('zone_trade_settings.bulk_updated', $zone, [
            'trade_ids' => array_map('intval', array_keys($this->tradeSettings)),
        ]);

        session()->flash('success', 'Tous les métiers ont été mis à jour pour cette zone.');
        $this->selectZone($zone->id);
    }

    public function toggleTradeActive(int $tradeId): void
    {
        $this->authorizeTradeZoneManagement();
        abort_unless($this->selectedZoneId !== null, 422);

        $zone = ServiceZone::findOrFail($this->selectedZoneId);
        $current = $this->tradeSettings[$tradeId] ?? [];

        $ouvertAujourdhui = TradeZonePricing::query()
            ->where('trade_id', $tradeId)
            ->where('service_zone_id', $zone->id)
            ->value('is_active');

        $payload = array_merge($current, [
            'is_active' => ! (bool) $ouvertAujourdhui,
        ]);

        $ligne = $this->persistTradeSetting($zone, $tradeId, $payload);

        ActivityLogger::log('zone_trade_setting.toggled', $zone, [
            'trade_id' => $tradeId,
            'is_active' => $ligne->fresh()->is_active,
        ]);

        session()->flash('success', $ligne->fresh()->is_active
            ? 'Métier activé dans cette zone.'
            : 'Métier désactivé dans cette zone.');
        $this->selectZone($zone->id);
    }
}
