<div class="space-y-4">

    <div class="grid gap-3 md:grid-cols-6">
        <select wire:model.live="status" class="border p-2 rounded">
            <option value="">Statut</option>
            <option value="pending">Pending</option>
            <option value="in_progress">En cours</option>
            <option value="completed">Terminé</option>
        </select>

        <select wire:model.live="qualityStatus" class="border p-2 rounded">
            <option value="">Qualité</option>
            <option value="excellent">Excellent</option>
            <option value="good">Good</option>
            <option value="warning">Warning</option>
            <option value="critical">Critical</option>
        </select>

        <input type="date" wire:model.live="dateFrom" class="border p-2 rounded">
        <input type="date" wire:model.live="dateTo" class="border p-2 rounded">
    </div>

    <div class="space-y-2">
        @foreach($missions as $mission)
            <div class="border p-3 rounded flex justify-between">
                <div>
                    {{ $mission->rendezVous?->booking_reference }}
                    <div class="text-sm text-gray-500">{{ $mission->status }}</div>
                </div>
                <div>{{ $mission->quality_score }}/100</div>
            </div>
        @endforeach
    </div>

    {{ $missions->links() }}
</div>