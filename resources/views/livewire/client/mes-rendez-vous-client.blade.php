<x-page-shell
    title="📅 Mes rendez-vous"
    subtitle="Retrouvez vos interventions à venir, modifiez-les si nécessaire et suivez leur statut.">
    <x-slot name="actions">
        <a
            href="{{ route('client.rendezvous.create') }}"
            class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
            ➕ Nouveau rendez-vous
        </a>
    </x-slot>


    <div class="bg-white rounded-2xl shadow border p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                <input
                    type="text"
                    wire:model.live="search"
                    placeholder="Service, ville, adresse..."
                    class="w-full border-gray-300 rounded-lg shadow-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                <select wire:model.live="filtreStatus" class="w-full border-gray-300 rounded-lg shadow-sm">
                    <option value="">— Tous —</option>
                    <option value="en_attente">En attente</option>
                    <option value="confirme">Confirmé</option>
                    <option value="en_route">En route</option>
                    <option value="sur_place">Sur place</option>
                    <option value="termine">Terminé</option>
                    <option value="refuse">Refusé</option>
                </select>
            </div>

            <div class="flex items-end">
                <button
                    wire:click="$set('tri', '{{ $tri === 'asc' ? 'desc' : 'asc' }}')"
                    class="inline-flex items-center px-4 py-2 rounded-lg border bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Trier : {{ $tri === 'asc' ? 'Croissant' : 'Décroissant' }}
                </button>
            </div>
        </div>
    </div>

    @if($editRdvId)
    <div class="bg-yellow-50 p-4 border border-yellow-300 rounded-xl shadow">
        <h4 class="font-semibold text-yellow-800 mb-3">✏️ Modifier le rendez-vous</h4>
        <div class="flex gap-4 items-end flex-wrap">
            <div>
                <label class="text-sm text-gray-700">Date</label>
                <input type="date" wire:model="editDate" class="text-sm border-gray-300 rounded px-2 py-1">
            </div>
            <div>
                <label class="text-sm text-gray-700">Heure</label>
                <input type="time" wire:model="editHeure" class="text-sm border-gray-300 rounded px-2 py-1">
            </div>
            <button wire:click="enregistrerModif" class="bg-blue-600 text-white px-3 py-1 rounded text-sm">
                💾 Sauvegarder
            </button>
            <button wire:click="fermerEdition" class="text-sm text-gray-600 underline">
                Annuler
            </button>
        </div>
    </div>
    @endif

    <div class="space-y-4">
        @forelse($rendezVous as $rdv)
        <div class="border rounded-2xl p-4 shadow-sm bg-gray-50 space-y-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                <div>
                    <h4 class="font-semibold text-gray-800 text-lg">
                        {{ $rdv->service_display_name }}
                    </h4>
                    <p class="text-sm text-gray-600">
                        📅 {{ $rdv->date }} à {{ $rdv->heure }}
                    </p>
                    <p class="text-sm text-gray-600">
                        🧑‍💼 {{ $rdv->employe->name ?? 'Employé à confirmer' }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <x-badge :status="$rdv->status" />
                    <x-priority-badge :priority="$rdv->priorite" />
                </div>
            </div>

            @if($rdv->recurring_series_id)
            <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-3 text-sm text-indigo-800">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold">🔁 Série récurrente</span>
                    <span>Position : #{{ $rdv->series_position ?? '—' }}</span>
                    <span>Statut série : {{ ucfirst($rdv->series_status ?? 'active') }}</span>
                </div>
            </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
                <div class="space-y-1">
                    <p><span class="font-medium">Type de lieu :</span> {{ ucfirst($rdv->type_lieu ?? '—') }}</p>
                    <p><span class="font-medium">Fréquence :</span> {{ ucfirst(str_replace('_', ' ', $rdv->frequence ?? '—')) }}</p>
                    <p><span class="font-medium">Surface :</span> {{ $rdv->surface ?? '—' }}</p>
                    <p><span class="font-medium">Durée estimée :</span> {{ $rdv->duree_estimee ? $rdv->duree_estimee . ' min' : '—' }}</p>
                </div>

                <div class="space-y-1">
                    <p><span class="font-medium">Adresse :</span> {{ $rdv->adresse ?? '—' }}</p>
                    <p><span class="font-medium">Ville :</span> {{ $rdv->ville ?? '—' }}</p>
                    <p><span class="font-medium">Code postal :</span> {{ $rdv->postal_code_display }}</p>
                    <p><span class="font-medium">Téléphone :</span> {{ $rdv->telephone_client ?? '—' }}</p>
                </div>
            </div>

            @if($rdv->commentaire_client)
            <div class="text-sm text-gray-700 bg-white border rounded-xl p-3">
                <span class="font-medium">Remarque :</span> {{ $rdv->commentaire_client }}
            </div>
            @endif

            <div class="bg-white border rounded-xl p-4 space-y-4">
                <p class="text-sm font-semibold text-slate-800">🧭 Suivi de mission</p>

                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="px-3 py-1 rounded-full {{ in_array($rdv->status, ['en_attente','confirme','en_route','sur_place','termine']) ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500' }}">
                        Demande reçue
                    </span>
                    <span class="px-3 py-1 rounded-full {{ in_array($rdv->status, ['confirme','en_route','sur_place','termine']) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        Confirmée
                    </span>
                    <span class="px-3 py-1 rounded-full {{ in_array($rdv->status, ['en_route','sur_place','termine']) ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}">
                        En route
                    </span>
                    <span class="px-3 py-1 rounded-full {{ in_array($rdv->status, ['sur_place','termine']) ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500' }}">
                        Sur place
                    </span>
                    <span class="px-3 py-1 rounded-full {{ $rdv->status === 'termine' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                        Terminée
                    </span>
                </div>

                @if($rdv->mission)
                    <livewire:client.mission-tracking :mission="$rdv->mission" :key="'mission-tracking-'.$rdv->mission->id" />
                @else
                    <p class="text-sm text-slate-500">Le suivi mission détaillé apparaîtra dès qu’une mission opérationnelle sera synchronisée.</p>
                @endif
            </div>

            <div class="flex flex-wrap gap-3 text-sm">
                <a href="{{ route('client.rendezvous.create', ['source_rdv' => $rdv->id]) }}" class="text-slate-700 underline">
                    🔁 Reprendre
                </a>

                <button wire:click="modifier({{ $rdv->id }})" class="text-blue-600 underline">
                    ✏️ Modifier
                </button>

                @if($rdv->recurring_series_id)
                <a href="{{ route('client.rendezvous.series.edit', $rdv->id) }}" class="text-indigo-600 underline">
                    🗓️ Gérer la série
                </a>
                @endif

                <button
                    wire:click="demanderAnnulation({{ $rdv->id }})"
                    class="text-red-600 underline">
                    ❌ Annuler
                </button>

                @if($rdv->status === 'termine' && $rdv->feedback)
                <span class="text-emerald-700">💬 Feedback laissé</span>
                @elseif($rdv->status === 'termine')
                <a href="{{ route('feedback.create', $rdv->id) }}" class="text-blue-600 underline">
                    💬 Laisser un feedback
                </a>
                @endif
            </div>
        </div>
        @empty
        <x-empty-state
            title="Aucun rendez-vous trouvé"
            message="Essayez un autre filtre ou créez un nouveau rendez-vous." />
        @endforelse
    </div>

    <div class="mt-4">
        {{ $rendezVous->links() }}
    </div>

    @if($cancelRdvId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Confirmer l’annulation</h3>
                    <p class="mt-1 text-sm text-slate-500">Ajoute une raison si tu veux garder une trace côté support.</p>
                </div>

                <textarea
                    wire:model.defer="cancelReason"
                    rows="4"
                    placeholder="Raison d’annulation (facultatif)..."
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:outline-none"
                ></textarea>

                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" wire:click="fermerAnnulation" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Retour</button>
                    <button type="button" wire:click="confirmerAnnulation" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white">Confirmer l’annulation</button>
                </div>
            </div>
        </div>
    @endif

</x-page-shell>