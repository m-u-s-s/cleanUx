<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="cu-card p-5">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-red-700">🚨 Urgences trop anciennes</h3>
                <p class="text-sm text-gray-500">Demandes urgentes encore bloquées en attente.</p>
            </div>
        </div>

        <div class="space-y-3">
            @forelse($urgencesVieillissantes as $rdv)
                <x-rdv-cleaning-card :rdv="$rdv">
                    <div class="flex flex-wrap gap-3 pt-2">
                        <button wire:click="ouvrirMission({{ $rdv->id }})" class="text-sm text-blue-600 underline">👁️ Voir détail</button>
                        <button wire:click="ouvrirPlanning({{ $rdv->id }})" class="text-sm text-amber-700 underline">🗓️ Replanifier</button>
                    </div>
                </x-rdv-cleaning-card>
            @empty
                <div class="text-sm italic text-gray-500">Aucune urgence en attente prolongée.</div>
            @endforelse
        </div>
    </div>

    <div class="cu-card p-5">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-orange-700">⏱️ Services sous-estimés</h3>
                <p class="text-sm text-gray-500">Services qui dépassent régulièrement la durée prévue.</p>
            </div>
        </div>

        <div class="space-y-3">
            @forelse($servicesSousEstimes as $service => $row)
                <div class="rounded-lg border bg-gray-50 p-4">
                    <p class="font-semibold text-gray-800">{{ ucfirst(str_replace('_', ' ', $service)) }}</p>
                    <p class="text-sm text-gray-600">Écart moyen : +{{ $row['avg_gap'] }} min</p>
                    <p class="text-sm text-gray-600">Base : {{ $row['count'] }} mission(s)</p>
                </div>
            @empty
                <div class="text-sm italic text-gray-500">Aucun service critique détecté.</div>
            @endforelse
        </div>
    </div>
</div>
