<x-page-shell
    title="Mes rendez-vous"
    subtitle="Gérez vos interventions, suivez l'employé, modifiez un créneau ou laissez un avis après la mission.">
    <x-slot name="actions">
        <a
            href="{{ route('client.rendezvous.create') }}"
            class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700"
            aria-label="{{ __('Créer un nouveau rendez-vous') }}">
            <x-ui.icon name="plus" class="w-4 h-4 mr-1.5" />
            {{ __('Nouveau rendez-vous') }}
        </a>
    </x-slot>

    <div aria-live="polite" aria-atomic="false">
        {{-- L'ESPACEMENT EST PORTE ICI : la coquille de page n'espace pas ses enfants, et les
             filtres touchaient la liste. La modale reste HORS de ce groupe — c'est une
             surcouche fixe, une marge la deplacerait. --}}
        <div class="space-y-6">
            @include('livewire.client.rendezvous.filters')
            @include('livewire.client.rendezvous.edit-panel')
            @include('livewire.client.rendezvous.list')
            @include('livewire.client.rendezvous.pagination')
        </div>

        @include('livewire.client.annulation.modale')
    </div>
</x-page-shell>
