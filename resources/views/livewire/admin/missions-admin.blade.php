<div class="p-4 md:p-6 space-y-6">
    <div>
        <div class="cu-hero">
            <div class="relative cu-toolbar gap-4">
                <div class="max-w-3xl">
                    <span class="cu-eyebrow">Pilotage opérationnel</span>
                    <h2 class="mt-3 text-2xl font-black tracking-tight text-slate-900 md:text-3xl">
                        Centre missions
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 md:text-base">
                        Vue consolidée des missions, des urgences, des non-assignées et de la charge opérationnelle.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow border p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="text" wire:model.live="search" placeholder="Service, client, employé, ville..."
                class="w-full border-gray-300 rounded-lg shadow-sm">

            <select wire:model.live="filtreEmploye" class="w-full border-gray-300 rounded-lg shadow-sm">
                <option value="">— Tous les employés —</option>
                @foreach($employes as $employe)
                <option value="{{ $employe->id }}">{{ $employe->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="filtreStatus" class="w-full border-gray-300 rounded-lg shadow-sm">
                <option value="">— Tous les statuts —</option>
                <option value="en_attente">En attente</option>
                <option value="confirme">Confirmé</option>
                <option value="en_route">En route</option>
                <option value="sur_place">Sur place</option>
                <option value="termine">Terminé</option>
                <option value="refuse">Refusé</option>
            </select>

            <select wire:model.live="filtrePriorite" class="w-full border-gray-300 rounded-lg shadow-sm">
                <option value="">— Toutes les priorités —</option>
                <option value="normale">Normale</option>
                <option value="haute">Haute</option>
                <option value="urgente">Urgente</option>
            </select>


        </div>
    </div>

    <div class="space-y-4">
        @forelse($missions as $rdv)
        <div class="bg-white border rounded-2xl shadow-sm p-4">
            <div class="flex flex-col md:flex-row md:justify-between gap-3">
                <div>
                    <p class="font-semibold text-slate-900 text-lg">
                        {{ $rdv->service_display_name }}
                    </p>
                    <p class="text-sm text-gray-600">
                        📅 {{ $rdv->date }} à {{ $rdv->heure }}
                    </p>
                    <p class="text-sm text-gray-600">
                        👤 {{ $rdv->client->name ?? '—' }} • 🧑‍💼 {{ $rdv->employe->name ?? '—' }}
                    </p>
                    <p class="text-sm text-gray-600">
                        📍 {{ $rdv->adresse ?? '—' }}, {{ $rdv->ville ?? '—' }}
                    </p>
                </div>

                <div class="flex items-start gap-2">
                    <x-badge :status="$rdv->status" />
                    <x-priority-badge :priority="$rdv->priorite" />
                </div>

                <button
                    type="button"
                    wire:click="dispatchRendezVous({{ $rdv->id }})"
                    class="rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    ⚡ Dispatch auto
                </button>
                <button
                    type="button"
                    wire:click="previewDispatch({{ $rdv->id }})"
                    class="rounded-xl border px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    👀 Voir scoring
                </button>


                <!-- modal -->
                @if($dispatchPreviewRdvId)
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
                    <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl space-y-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Scoring dispatch</h3>
                                <p class="text-sm text-slate-500">Classement des employés disponibles.</p>
                            </div>

                            <button wire:click="closeDispatchPreview" class="text-slate-500 hover:text-slate-800">
                                ✕
                            </button>
                        </div>

                        <div class="space-y-2">
                            @forelse($dispatchPreview as $row)
                            <div class="flex items-center justify-between rounded-xl border p-3">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $row['name'] }}</p>
                                    <p class="text-xs {{ $row['available'] ? 'text-emerald-600' : 'text-red-600' }}">
                                        {{ $row['available'] ? 'Disponible' : 'Indisponible' }}
                                    </p>
                                </div>

                                <div class="text-right">
                                    <p class="text-xl font-bold text-indigo-700">{{ $row['score'] }}</p>
                                    <p class="text-xs text-slate-500">score</p>
                                </div>
                            </div>
                            @empty
                            <p class="text-sm text-slate-500">Aucun employé trouvé.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                @endif

            </div>
        </div>
        @empty
        <div class="bg-white border rounded-xl p-6 text-center text-gray-500 italic">
            Aucune mission trouvée.
        </div>
        @endforelse
    </div>

    <div>
        {{ $missions->links() }}
    </div>
</div>