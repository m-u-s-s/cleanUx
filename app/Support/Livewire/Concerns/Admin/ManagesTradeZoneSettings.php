<?php

namespace App\Support\Livewire\Concerns\Admin;

use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZoneSetting;
use App\Support\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * Pilote l'activation/désactivation et la tarification d'un métier (Trade)
 * dans une zone (ServiceZone). Absence de ligne en base = trade implicitement
 * actif avec multiplicateur 1.00 (back-compat).
 *
 * Doit être branché dans un composant Livewire qui expose $selectedZoneId.
 * Le composant doit appeler $this->loadTradeSettingsForZone($zoneId) après
 * chaque selectZone() (cf. trait alias dans GestionZones).
 */
trait ManagesTradeZoneSettings
{
    public array $tradeSettings = [];

    protected function loadTradeSettingsForZone(?int $zoneId): void
    {
        if (! $zoneId) {
            $this->tradeSettings = [];
            return;
        }

        $existing = TradeZoneSetting::query()
            ->where('service_zone_id', $zoneId)
            ->get()
            ->keyBy('trade_id');

        $this->tradeSettings = Trade::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (Trade $trade) use ($existing) {
                $setting = $existing->get($trade->id);

                return [
                    $trade->id => [
                        'trade_name'       => (string) $trade->name,
                        'trade_slug'       => (string) $trade->slug,
                        'trade_color'      => (string) ($trade->color ?: '#64748b'),
                        'trade_icon'       => (string) ($trade->icon ?: 'briefcase'),
                        'is_active'        => $setting === null ? true : (bool) $setting->is_active,
                        'price_multiplier' => $setting?->price_multiplier !== null
                            ? (string) $setting->price_multiplier
                            : '1.00',
                        'notes'            => (string) ($setting?->notes ?? ''),
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

    protected function persistTradeSetting(ServiceZone $zone, int $tradeId, array $payload): TradeZoneSetting
    {
        return TradeZoneSetting::updateOrCreate(
            ['trade_id' => $tradeId, 'service_zone_id' => $zone->id],
            [
                'is_active'        => (bool) ($payload['is_active'] ?? true),
                'price_multiplier' => filled($payload['price_multiplier'] ?? null)
                    ? (float) $payload['price_multiplier']
                    : 1.00,
                'notes'            => filled($payload['notes'] ?? null) ? $payload['notes'] : null,
                'updated_by'       => auth()->id(),
                'created_by'       => auth()->id(),
            ]
        );
    }

    public function saveTradeSetting(int $tradeId): void
    {
        $this->authorizeTradeZoneManagement();

        $this->validate([
            'selectedZoneId'                            => ['required', 'exists:service_zones,id'],
            "tradeSettings.$tradeId.is_active"          => ['boolean'],
            "tradeSettings.$tradeId.price_multiplier"   => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            "tradeSettings.$tradeId.notes"              => ['nullable', 'string', 'max:1000'],
        ]);

        $zone = ServiceZone::findOrFail($this->selectedZoneId);
        Trade::findOrFail($tradeId); // valide que le trade existe

        $payload = $this->tradeSettings[$tradeId] ?? [];
        $setting = $this->persistTradeSetting($zone, $tradeId, $payload);

        ActivityLogger::log('zone_trade_setting.updated', $zone, [
            'trade_id'   => $tradeId,
            'setting_id' => $setting->id,
            'payload'    => Arr::only($payload, ['is_active', 'price_multiplier', 'notes']),
        ]);

        session()->flash('success', 'Métier mis à jour pour cette zone.');
        $this->selectZone($zone->id);
    }

    public function saveAllTradeSettings(): void
    {
        $this->authorizeTradeZoneManagement();

        $this->validate([
            'selectedZoneId'                       => ['required', 'exists:service_zones,id'],
            'tradeSettings.*.is_active'            => ['boolean'],
            'tradeSettings.*.price_multiplier'     => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            'tradeSettings.*.notes'                => ['nullable', 'string', 'max:1000'],
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
        $payload = array_merge($current, [
            'is_active' => ! (bool) ($current['is_active'] ?? true),
        ]);

        $setting = $this->persistTradeSetting($zone, $tradeId, $payload);

        ActivityLogger::log('zone_trade_setting.toggled', $zone, [
            'trade_id'  => $tradeId,
            'is_active' => $setting->fresh()->is_active,
        ]);

        session()->flash('success', $setting->fresh()->is_active
            ? 'Métier activé dans cette zone.'
            : 'Métier désactivé dans cette zone.');
        $this->selectZone($zone->id);
    }
}
