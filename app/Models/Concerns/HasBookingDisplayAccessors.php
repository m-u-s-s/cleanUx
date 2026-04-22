<?php

namespace App\Models\Concerns;

trait HasBookingDisplayAccessors
{
    public function getServiceLabelAttribute(): string
    {
        return $this->service_display_name;
    }

    public function getServiceIdentifierAttribute(): string
    {
        return (string) (
            data_get($this->pricing_snapshot, 'service_identifier')
            ?: data_get($this->pricing_snapshot, 'service.service_identifier')
            ?: $this->serviceCatalog?->code
            ?: $this->serviceCatalog?->slug
            ?: data_get($this->pricing_snapshot, 'service.code')
            ?: data_get($this->pricing_snapshot, 'service.slug')
            ?: ''
        );
    }

    public function getBookingPostalCodeAttribute(): string
    {
        return (string) ($this->postalCode?->code ?: $this->code_postal ?: '');
    }

    public function getServiceDisplayNameAttribute(): string
    {
        $serviceName = $this->serviceCatalog?->display_name
            ?: $this->serviceCatalog?->name
            ?: data_get($this->pricing_snapshot, 'service_name')
            ?: data_get($this->pricing_snapshot, 'service.name')
            ?: $this->motif;

        if (! filled($serviceName)) {
            return 'Service non précisé';
        }

        return (string) str($serviceName)->replace('_', ' ')->headline();
    }

    public function getPostalCodeDisplayAttribute(): string
    {
        return (string) ($this->code_postal ?: $this->postalCode?->code ?: '—');
    }

    public function getServiceIdentifierDisplayAttribute(): string
    {
        return (string) (
            data_get($this->pricing_snapshot, 'service_identifier')
            ?: data_get($this->pricing_snapshot, 'service.service_identifier')
            ?: $this->serviceCatalog?->code
            ?: $this->serviceCatalog?->slug
            ?: data_get($this->pricing_snapshot, 'service.code')
            ?: data_get($this->pricing_snapshot, 'service.slug')
            ?: '—'
        );
    }

    public function getLocationLineAttribute(): string
    {
        return $this->location_display;
    }

    public function getLocationDisplayAttribute(): string
    {
        $parts = array_filter([
            $this->adresse,
            $this->postal_code_display !== '—' ? $this->postal_code_display : null,
            $this->ville,
        ]);

        return $parts !== [] ? implode(', ', $parts) : 'Adresse non précisée';
    }
}
