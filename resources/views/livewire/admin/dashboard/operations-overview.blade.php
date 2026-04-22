<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="cu-card p-5">
        <h3 class="mb-4 text-lg font-semibold text-blue-900">📍 Interventions du jour</h3>

        <div class="space-y-4">
            @forelse($interventionsDuJour as $rdv)
                <x-rdv-cleaning-card :rdv="$rdv">
                    <div class="flex flex-wrap gap-3 pt-2">
                        <button wire:click="ouvrirMission({{ $rdv->id }})" class="text-sm text-blue-600 underline">👁️ Voir détail</button>

                        @if(in_array($rdv->status, ['en_attente', 'confirme', 'en_route', 'sur_place']))
                            <button wire:click="ouvrirPlanning({{ $rdv->id }})" class="text-sm text-amber-700 underline">🗓️ Replanifier</button>
                        @endif
                    </div>
                </x-rdv-cleaning-card>
            @empty
                <div class="text-sm italic text-gray-500">Aucune intervention prévue aujourd’hui.</div>
            @endforelse
        </div>
    </div>

    <div class="cu-card p-5">
        <h3 class="mb-4 text-lg font-semibold text-blue-900">📊 Charge des employés aujourd’hui</h3>

        <div class="space-y-3">
            @forelse($chargeEmployes as $item)
                <div class="flex items-center justify-between rounded-lg border bg-gray-50 p-3">
                    <div>
                        <p class="font-semibold text-gray-800">{{ $item['employe']->name }}</p>
                        <p class="text-sm text-gray-600">{{ $item['count'] }} intervention(s) • {{ $item['minutes'] }} min • {{ $item['hours'] }} h</p>
                    </div>

                    <div>
                        @if($item['minutes'] >= 480)
                            <span class="inline-flex items-center rounded-full border border-red-200 bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">Surchargé</span>
                        @elseif($item['minutes'] >= 300)
                            <span class="inline-flex items-center rounded-full border border-orange-200 bg-orange-100 px-2.5 py-1 text-xs font-semibold text-orange-700">Chargé</span>
                        @else
                            <span class="inline-flex items-center rounded-full border border-green-200 bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">OK</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-sm italic text-gray-500">Aucun employé trouvé.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="cu-card p-5">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-red-700">🚨 Interventions urgentes</h3>
                <p class="text-sm text-gray-500">Demandes prioritaires à traiter rapidement.</p>
            </div>
        </div>

        <div class="space-y-4">
            @forelse($urgences as $rdv)
                <x-rdv-cleaning-card :rdv="$rdv">
                    <div class="flex flex-wrap gap-3 pt-2">
                        <button wire:click="ouvrirMission({{ $rdv->id }})" class="text-sm text-blue-600 underline">👁️ Voir détail</button>

                        @if(in_array($rdv->status, ['en_attente', 'confirme', 'en_route', 'sur_place']))
                            <button wire:click="ouvrirPlanning({{ $rdv->id }})" class="text-sm text-amber-700 underline">🗓️ Replanifier</button>
                        @endif
                    </div>
                </x-rdv-cleaning-card>
            @empty
                <div class="text-sm italic text-gray-500">Aucune intervention urgente pour le moment.</div>
            @endforelse
        </div>
    </div>

    <div class="cu-card p-5">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-emerald-700">✅ Missions terminées récemment</h3>
                <p class="text-sm text-gray-500">Contrôle rapide des dernières interventions clôturées.</p>
            </div>
        </div>

        <div class="space-y-4">
            @forelse($missionsTerminees as $rdv)
                <x-rdv-cleaning-card :rdv="$rdv">
                    <div class="flex flex-wrap gap-3 pt-2">
                        <button wire:click="ouvrirMission({{ $rdv->id }})" class="text-sm text-blue-600 underline">👁️ Voir détail</button>

                        @if(in_array($rdv->status, ['en_attente', 'confirme', 'en_route', 'sur_place']))
                            <button wire:click="ouvrirPlanning({{ $rdv->id }})" class="text-sm text-amber-700 underline">🗓️ Replanifier</button>
                        @endif
                    </div>
                </x-rdv-cleaning-card>
            @empty
                <div class="text-sm italic text-gray-500">Aucune mission terminée récemment.</div>
            @endforelse
        </div>
    </div>
</div>
