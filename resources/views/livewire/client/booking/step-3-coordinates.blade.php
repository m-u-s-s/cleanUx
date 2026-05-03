<div class="space-y-6">
    <div>
        <p class="text-sm font-semibold text-sky-700">Étape 3</p>
        <h2 class="mt-1 text-xl font-bold text-slate-900">Adresse et contact</h2>
        <p class="mt-1 text-sm text-slate-500">La couverture de zone est vérifiée à partir de l’adresse et du code postal.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @include('livewire.client.booking.coordinates.address')
        @include('livewire.client.booking.coordinates.city-postal')
        @include('livewire.client.booking.coordinates.coverage')
        @include('livewire.client.booking.coordinates.contact-priority')
    </div>

    @include('livewire.client.booking.coordinates.google-places-script')
</div>
