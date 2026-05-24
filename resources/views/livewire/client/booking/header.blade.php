<div class="px-6 md:px-8 py-6 border-b border-slate-100 bg-gradient-to-br from-brand-50/30 to-white">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-brand-700">
                <x-ui.icon name="calendar" class="w-3.5 h-3.5" />
                Réservation {{ $this->isPremiumClient() ? 'Premium' : 'Standard' }}
            </p>
            <h1 class="mt-2 text-2xl md:text-3xl font-bold tracking-tight text-slate-900">
                Planifier une prestation
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Remplissez votre demande en {{ count([1,2,3,4,5]) }} étapes simples.
            </p>
        </div>

        @include('livewire.client.booking.inline-alerts')
    </div>

    @include('livewire.client.booking.stepper')
</div>
