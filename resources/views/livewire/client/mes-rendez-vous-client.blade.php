<x-page-shell
    title="📅 Mes rendez-vous"
    subtitle="Gérez vos interventions, suivez l’employé, modifiez un créneau ou laissez un avis après la mission.">
    <x-slot name="actions">
        <a
            href="{{ route('client.rendezvous.create') }}"
            class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
            ➕ Nouveau rendez-vous
        </a>
    </x-slot>

    @include('livewire.client.rendezvous.filters')
    @include('livewire.client.rendezvous.edit-panel')
    @include('livewire.client.rendezvous.list')
    @include('livewire.client.rendezvous.pagination')
    @include('livewire.client.rendezvous.cancel-modal')
</x-page-shell>
