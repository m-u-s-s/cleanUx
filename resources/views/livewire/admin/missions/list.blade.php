<div class="space-y-4">
    @forelse($missions as $rdv)
        @include('livewire.admin.missions.mission-card', ['rdv' => $rdv])
    @empty
        <div class="bg-white border rounded-xl p-6 text-center text-gray-500 italic">
            Aucune mission trouvée.
        </div>
    @endforelse
</div>
