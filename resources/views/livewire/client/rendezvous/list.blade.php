<div class="space-y-4">
    @forelse($rendezVous as $rdv)
        @include('livewire.client.rendezvous.appointment-card', ['rdv' => $rdv])
    @empty
        <x-empty-state
            title="Aucun rendez-vous trouvé"
            message="Essayez un autre filtre ou créez un nouveau rendez-vous." />
    @endforelse
</div>
