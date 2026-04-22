<div class="px-6 md:px-8 py-6 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-medium text-sky-600">
                Réservation {{ $this->isPremiumClient() ? 'Premium' : 'Standard' }}
            </p>
            <h1 class="text-2xl md:text-3xl font-bold text-slate-900">
                Planifier une prestation
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Remplissez votre demande en quelques étapes.
            </p>
        </div>

        @include('livewire.client.booking.inline-alerts')
    </div>

    @include('livewire.client.booking.stepper')
</div>
