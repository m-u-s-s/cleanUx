<div class="space-y-6">
    <div>
        <p class="text-sm font-semibold text-sky-700">Étape 4</p>
        <h2 class="mt-1 text-xl font-bold text-slate-900">Choisissez le créneau</h2>
        <p class="mt-1 text-sm text-slate-500">Sélectionnez un mode de réservation, une date et les préférences de récurrence.</p>
    </div>

    @include('livewire.client.booking.scheduling.employee-choice')
    @include('livewire.client.booking.scheduling.mode-selector')
    @include('livewire.client.booking.scheduling.asap-message')
    @include('livewire.client.booking.scheduling.date-time-fields')
    @include('livewire.client.booking.scheduling.recurrence-panel')
</div>
