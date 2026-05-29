<?php

namespace App\Support\Livewire\Concerns\Booking;

use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait ProvidesBookingFormOptions
{
    public function getSurfacesProperty(): array
    {
        return [
            'moins_50' => 'Moins de 50 m²',
            '50_100' => '50 à 100 m²',
            '100_150' => '100 à 150 m²',
            '150_250' => '150 à 250 m²',
            'plus_250' => 'Plus de 250 m²',
        ];
    }

    public function getServicesProperty(): array
    {
        $resolvedServiceZoneId = $this->resolvedServiceZoneId ?? null;

        $query = ServiceCatalog::query()
            ->with('trade:id,name,slug,sort_order')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($resolvedServiceZoneId) {
            $query->whereHas('zoneServiceRules', function ($ruleQuery) use ($resolvedServiceZoneId) {
                $ruleQuery
                    ->where('service_zone_id', $resolvedServiceZoneId)
                    ->where('is_enabled', true);
            });

            $disabledTradeIds = $this->disabledTradeIdsForZone($resolvedServiceZoneId);
            if ($disabledTradeIds->isNotEmpty()) {
                $query->whereNotIn('trade_id', $disabledTradeIds);
            }
        }

        $catalogs = $query->get();

        // Pas de service ? Fallback flat — la vue gère "Autres"
        if ($catalogs->isEmpty()) {
            return [
                'Nettoyage' => [
                    'nettoyage_standard' => 'Nettoyage standard',
                ],
            ];
        }

        if ($catalogs->isNotEmpty()) {
            return $catalogs
                ->mapWithKeys(fn (ServiceCatalog $service) => [
                    ($service->code ?: $service->slug) => $service->name,
                ])
                ->toArray();
        }

        return [
            'nettoyage_standard' => 'Nettoyage standard',
            'nettoyage_profond' => 'Nettoyage en profondeur',
            'fin_de_chantier' => 'Nettoyage fin de chantier',
            'fin_de_bail' => 'Nettoyage fin de bail',
            'bureaux' => 'Nettoyage bureaux / professionnels',
        ];
    }

    /**
     * Phase 1 multi-métiers — version groupée par Trade pour rendu en
     * <optgroup>. Ne remplace PAS getServicesProperty() (back-compat) :
     * cette méthode est exposée en plus, sous le nom $servicesGroupedByTrade,
     * et utilisée uniquement par la vue de réservation.
     *
     * Format retourné :
     * [
     *   'Nettoyage' => ['nettoyage_standard' => 'Nettoyage standard', ...],
     *   'Peinture'  => ['peinture_interieure' => 'Peinture intérieure', ...],
     *   'Autres'    => [...services sans trade rattaché...],
     * ]
     *
     * Les services sans trade sont regroupés sous "Autres" (clé fallback)
     * pour ne PAS être perdus pendant la phase de transition multi-métiers.
     */
    public function getServicesGroupedByTradeProperty(): array
    {
        $resolvedServiceZoneId = $this->resolvedServiceZoneId ?? null;
        $query = ServiceCatalog::query()
            ->with('trade:id,name,slug,sort_order')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($resolvedServiceZoneId) {
            $query->whereHas('zoneServiceRules', function ($ruleQuery) use ($resolvedServiceZoneId) {
                $ruleQuery
                    ->where('service_zone_id', $resolvedServiceZoneId)
                    ->where('is_enabled', true);
            });

            $disabledTradeIds = $this->disabledTradeIdsForZone($resolvedServiceZoneId);
            if ($disabledTradeIds->isNotEmpty()) {
                $query->whereNotIn('trade_id', $disabledTradeIds);
            }
        }

        $catalogs = $query->get();

        // Pas de service ? Fallback flat — la vue gère "Autres"
        if ($catalogs->isEmpty()) {
            return [
                'Nettoyage' => [
                    'nettoyage_standard' => 'Nettoyage standard',
                    'nettoyage_profond' => 'Nettoyage en profondeur',
                    'fin_de_chantier' => 'Nettoyage fin de chantier',
                    'fin_de_bail' => 'Nettoyage fin de bail',
                    'bureaux' => 'Nettoyage bureaux / professionnels',
                ],
            ];
        }

        // Group by trade name, preserve trade sort_order via collection
        return $catalogs
            ->groupBy(fn (ServiceCatalog $s) => $s->trade?->name ?: 'Autres')
            ->map(fn ($group) => $group->mapWithKeys(fn (ServiceCatalog $s) => [
                ($s->code ?: $s->slug) => $s->name,
            ])->toArray())
            ->sortBy(function ($_, $tradeName) use ($catalogs) {
                // "Autres" en dernier, sinon respecter trade.sort_order
                if ($tradeName === 'Autres') {
                    return PHP_INT_MAX;
                }
                $first = $catalogs->first(fn (ServiceCatalog $s) => $s->trade?->name === $tradeName);

                return $first?->trade?->sort_order ?? 0;
            })
            ->toArray();
    }

    public function updatedPostalCodeInput(): void
    {
        $this->resolveCoverageContext();
        $this->chargerEmployesDisponibles();
        $this->chargerCreneauxDisponibles();
        $this->refreshEstimations();
    }

    public function updatedVille(): void
    {
        $this->resolveCoverageContext();
        $this->chargerEmployesDisponibles();
        $this->chargerCreneauxDisponibles();
        $this->refreshEstimations();
    }

    public function getTypesLieuxProperty(): array
    {
        return [
            'appartement' => 'Appartement',
            'maison' => 'Maison',
            'bureau' => 'Bureau',
            'commerce' => 'Commerce',
            'autre' => 'Autre',
        ];
    }

    protected function makeReference(?string $prefix = 'CUX'): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    public function getFrequencesProperty(): array
    {
        return [
            'ponctuel' => 'Ponctuel',
            'hebdomadaire' => 'Hebdomadaire',
            'bihebdomadaire' => 'Toutes les 2 semaines',
            'mensuel' => 'Mensuel',
        ];
    }

    public function getPrioritesProperty(): array
    {
        return [
            'normale' => 'Normale',
            'haute' => 'Haute',
            'urgente' => 'Urgente',
        ];
    }

    public function getOptionsDisponiblesProperty(): array
    {
        return [
            'vitres' => 'Vitres',
            'frigo' => 'Frigo',
            'four' => 'Four',
            'repassage' => 'Repassage',
            'desinfection' => 'Désinfection',
        ];
    }

    public function getZonesDisponiblesProperty(): array
    {
        return [
            'cuisine' => 'Cuisine',
            'salle_de_bain' => 'Salle de bain',
            'salon' => 'Salon',
            'chambres' => 'Chambres',
            'bureau' => 'Bureau',
            'escaliers' => 'Escaliers',
        ];
    }

    protected function eligibleEmployeesQuery(?int $zoneId = null)
    {
        return $this->employeeAvailabilityService()->eligibleEmployeesQuery($zoneId);
    }

    protected function employeeCoverageScore(User $employee, int $zoneId): int
    {
        return $this->employeeAvailabilityService()->employeeCoverageScore($employee, $zoneId);
    }

    protected function sortedEligibleEmployeesForZone(int $zoneId)
    {
        return $this->employeeAvailabilityService()->sortedEligibleEmployeesForZone($zoneId);
    }

    public function getEmployesProperty()
    {
        $zoneId = $this->resolvedServiceZoneId ?: $this->currentBookableServiceZone()?->id;
        $employes = $zoneId
            ? $this->sortedEligibleEmployeesForZone($zoneId)
            : $this->eligibleEmployeesQuery()->get();

        if (! $this->isPremiumClient() || ! Auth::check()) {
            return $employes;
        }

        $favoriteIds = Auth::user()->favoriteEmployes()->pluck('users.id')->toArray();

        $favorites = $employes->filter(fn ($e) => in_array($e->id, $favoriteIds));
        $others = $employes->reject(fn ($e) => in_array($e->id, $favoriteIds));

        return $favorites->concat($others)->values();
    }

    public function getSelectedServiceLabelProperty(): string
    {
        $catalog = $this->currentServiceCatalog();

        if ($catalog?->name) {
            return (string) $catalog->name;
        }

        $label = $this->services[$this->selected_service_identifier] ?? null;

        if (filled($label)) {
            return (string) $label;
        }

        return '—';
    }
}
