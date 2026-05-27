<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    {{-- Phase F4 — pass filtered services when a trade is selected --}}
    @include('livewire.client.booking.service.field-service', [
        'groupedServices' => $servicesForSelectedTrade ?? $servicesGroupedByTrade ?? [],
    ])
    {{-- Phase F3 — les 3 champs cleaning ci-dessous sont remplacés par
         le schema dynamique du trade choisi (rendu en step 2). On les
         cache si le trade a déjà un schema actif. --}}
    @unless($this->hasTradeFormSchema())
        @include('livewire.client.booking.service.field-location-type')
        @include('livewire.client.booking.service.field-frequency')
        @include('livewire.client.booking.service.field-surface')
    @endunless
</div>
