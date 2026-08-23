<?php

namespace App\Livewire\Admin;

use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Support\ActivityLogger;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin — Tarification par zone pour un métier donné. */
#[Layout('layouts.app')]
class TradeZonePricingManager extends Component
{
    use EnforcesAdminAccess;

    public int $tradeId;

    public string $tradeName = '';

    // ── Inline edit state ──
    public ?int $editingId = null;

    // ── Form fields ──
    public string $form_base_rate_cents = '0';

    public string $form_surge_multiplier = '1.00';

    public string $form_min_price_cents = '';

    public string $form_max_price_cents = '';

    public bool $form_is_active = true;

    // LE PRIX AU KILOMETRE — eteint par defaut, y compris ici.
    public bool $form_distance_pricing_enabled = false;

    public string $form_pickup_fee_cents = '0';

    public string $form_price_per_km_cents = '';

    public string $form_price_per_minute_cents = '';

    public string $form_included_km = '0';

    // LE TARIF HORAIRE DE LA ZONE — la surcharge que le formulaire du métier promettait déjà.
    public string $form_price_per_hour_cents = '';

    // ── Add zone dropdown ──
    public ?int $addZoneId = null;

    public function mount(Trade $trade): void
    {
        $this->tradeId = $trade->id;
        $this->tradeName = (string) $trade->name;
    }

    public function loadPricings(): void
    {
        // Refresh is automatic through render(); called for explicit resets.
    }

    public function edit(int $id): void
    {
        $pricing = TradeZonePricing::findOrFail($id);

        $this->editingId = $id;
        $this->form_base_rate_cents = (string) $pricing->base_rate_cents;
        $this->form_surge_multiplier = (string) $pricing->surge_multiplier;
        $this->form_min_price_cents = $pricing->min_price_cents !== null ? (string) $pricing->min_price_cents : '';
        $this->form_max_price_cents = $pricing->max_price_cents !== null ? (string) $pricing->max_price_cents : '';
        $this->form_is_active = (bool) $pricing->is_active;
        $this->form_distance_pricing_enabled = (bool) $pricing->distance_pricing_enabled;
        $this->form_pickup_fee_cents = (string) $pricing->pickup_fee_cents;
        // Chaine vide, pas zero : « aucun tarif au kilometre » et « zero centime le kilometre » ne
        // disent pas la meme chose, et le second facturerait la distance gratuitement.
        $this->form_price_per_km_cents = $pricing->getRawOriginal('price_per_km_cents') !== null
            ? (string) $pricing->getRawOriginal('price_per_km_cents')
            : '';
        $this->form_price_per_minute_cents = $pricing->getRawOriginal('price_per_minute_cents') !== null
            ? (string) $pricing->getRawOriginal('price_per_minute_cents')
            : '';
        $this->form_included_km = (string) $pricing->included_km;
        $this->form_price_per_hour_cents = $pricing->getRawOriginal('price_per_hour_cents') !== null
            ? (string) $pricing->getRawOriginal('price_per_hour_cents')
            : '';
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->resetFormFields();
    }

    public function save(): void
    {
        $this->validate([
            'form_base_rate_cents' => ['required', 'integer', 'min:0', 'max:9999900'],
            'form_surge_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'form_min_price_cents' => ['nullable', 'integer', 'min:0', 'max:9999900'],
            'form_max_price_cents' => ['nullable', 'integer', 'min:0', 'max:9999900'],
            'form_is_active' => ['boolean'],
            'form_distance_pricing_enabled' => ['boolean'],
            'form_pickup_fee_cents' => ['nullable', 'integer', 'min:0', 'max:9999900'],
            'form_price_per_km_cents' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'form_price_per_minute_cents' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'form_included_km' => ['nullable', 'integer', 'min:0', 'max:1000'],
            // Meme plafond que le tarif de base : 99 999 EUR de l'heure est absurde, mais c'est la
            // borne que le reste de cet ecran applique deja, et en poser une autre ici ferait
            // accepter par un champ ce qu'un autre refuse.
            'form_price_per_hour_cents' => ['nullable', 'integer', 'min:0', 'max:9999900'],
        ]);

        $data = [
            'base_rate_cents' => (int) $this->form_base_rate_cents,
            'surge_multiplier' => (float) $this->form_surge_multiplier,
            'min_price_cents' => $this->form_min_price_cents !== '' ? (int) $this->form_min_price_cents : null,
            'max_price_cents' => $this->form_max_price_cents !== '' ? (int) $this->form_max_price_cents : null,
            'is_active' => $this->form_is_active,
            'distance_pricing_enabled' => $this->form_distance_pricing_enabled,
            'pickup_fee_cents' => $this->form_pickup_fee_cents !== '' ? (int) $this->form_pickup_fee_cents : 0,
            'price_per_km_cents' => $this->form_price_per_km_cents !== '' ? (int) $this->form_price_per_km_cents : null,
            'price_per_minute_cents' => $this->form_price_per_minute_cents !== '' ? (int) $this->form_price_per_minute_cents : null,
            'included_km' => $this->form_included_km !== '' ? (int) $this->form_included_km : 0,
            'price_per_hour_cents' => $this->form_price_per_hour_cents !== ''
                ? (int) $this->form_price_per_hour_cents
                : null,
        ];

        $pricing = TradeZonePricing::findOrFail($this->editingId);
        $pricing->update($data);
        ActivityLogger::log('admin.trade_zone_pricing.updated', $pricing, ['changes' => $data]);

        $this->editingId = null;
        $this->resetFormFields();
        session()->flash('success', 'Tarif mis à jour.');
    }

    public function toggleActive(int $id): void
    {
        $pricing = TradeZonePricing::findOrFail($id);
        $pricing->is_active = ! $pricing->is_active;
        $pricing->save();

        ActivityLogger::log('admin.trade_zone_pricing.toggle_active', $pricing, [
            'is_active' => $pricing->is_active,
        ]);
    }

    public function addZone(int $serviceZoneId): void
    {
        $exists = TradeZonePricing::query()
            ->where('trade_id', $this->tradeId)
            ->where('service_zone_id', $serviceZoneId)
            ->exists();

        if ($exists) {
            session()->flash('error', 'Cette zone est déjà configurée pour ce métier.');

            return;
        }

        $trade = Trade::findOrFail($this->tradeId);
        $defaultCents = $trade->default_hourly_rate !== null
            ? (int) round((float) $trade->default_hourly_rate * 100)
            : 0;

        $pricing = TradeZonePricing::create([
            'trade_id' => $this->tradeId,
            'service_zone_id' => $serviceZoneId,
            'base_rate_cents' => $defaultCents,
            'surge_multiplier' => '1.00',
            'min_price_cents' => null,
            'max_price_cents' => null,
            'is_active' => true,
        ]);

        ActivityLogger::log('admin.trade_zone_pricing.created', $pricing);
        $this->addZoneId = null;
        session()->flash('success', 'Zone ajoutée avec les valeurs par défaut.');
    }

    public function delete(int $id): void
    {
        $pricing = TradeZonePricing::findOrFail($id);
        $pricing->delete();
        ActivityLogger::log('admin.trade_zone_pricing.deleted', $pricing);

        if ($this->editingId === $id) {
            $this->editingId = null;
            $this->resetFormFields();
        }

        session()->flash('success', 'Tarif zone supprimé.');
    }

    public function render(): View
    {
        $zonePricings = TradeZonePricing::query()
            ->where('trade_id', $this->tradeId)
            ->with('serviceZone')
            ->orderBy('id')
            ->get();

        // Zones not yet configured for this trade
        $configuredZoneIds = $zonePricings->pluck('service_zone_id');
        $availableZones = ServiceZone::query()
            ->whereNotIn('id', $configuredZoneIds)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('livewire.admin.trade-zone-pricing-manager', [
            'zonePricings' => $zonePricings,
            'availableZones' => $availableZones,
            // LE TARIF HORAIRE NE S'AFFICHE QUE S'IL VEUT DIRE QUELQUE CHOSE.
            'factureALHeure' => (bool) Trade::query()->whereKey($this->tradeId)->value('hourly_billing'),
        ]);
    }

    private function resetFormFields(): void
    {
        $this->form_base_rate_cents = '0';
        $this->form_surge_multiplier = '1.00';
        $this->form_min_price_cents = '';
        $this->form_max_price_cents = '';
        $this->form_is_active = true;
        $this->form_distance_pricing_enabled = false;
        $this->form_pickup_fee_cents = '0';
        $this->form_price_per_km_cents = '';
        $this->form_price_per_minute_cents = '';
        $this->form_included_km = '0';
        $this->form_price_per_hour_cents = '';
        $this->resetErrorBag();
    }
}
