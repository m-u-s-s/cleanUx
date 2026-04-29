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


    <div class="bg-white rounded-2xl shadow-sm border p-5">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Recherche</label>
                <input
                    type="text"
                    wire:model.live.debounce.350ms="search"
                    placeholder="Service, ville, adresse, employé..."
                    class="w-full rounded-xl border-gray-300 shadow-sm text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Statut</label>
                <select wire:model.live="filtreStatus" class="w-full rounded-xl border-gray-300 shadow-sm text-sm">
                    <option value="">Tous</option>
                    <option value="en_attente">En attente</option>
                    <option value="confirme">Confirmé</option>
                    <option value="en_route">En route</option>
                    <option value="sur_place">Sur place</option>
                    <option value="termine">Terminé</option>
                    <option value="refuse">Refusé</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tri</label>
                <select wire:model.live="tri" class="w-full rounded-xl border-gray-300 shadow-sm text-sm">
                    <option value="asc">Plus proche d’abord</option>
                    <option value="desc">Plus récent d’abord</option>
                </select>
            </div>
        </div>
    </div>

    @if($editRdvId)
    <div class="bg-yellow-50 p-5 border border-yellow-300 rounded-2xl shadow space-y-4">
        <div>
            <h4 class="font-semibold text-yellow-900">🔁 Replanifier le rendez-vous</h4>
            <p class="text-sm text-yellow-700">
                Choisis une nouvelle date et un créneau disponible. Le rendez-vous repassera en attente de confirmation.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-medium text-gray-700">Nouvelle date</label>
                <input
                    type="date"
                    wire:model.live="editDate"
                    class="w-full text-sm border-gray-300 rounded-lg px-3 py-2">
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700">Heure sélectionnée</label>
                <input
                    type="time"
                    wire:model="editHeure"
                    class="w-full text-sm border-gray-300 rounded-lg px-3 py-2">
            </div>
        </div>

        <div>
            <p class="text-sm font-semibold text-gray-800 mb-2">Créneaux disponibles</p>

            @if(count($creneauxDisponibles))
            <div class="flex flex-wrap gap-2">
                @foreach($creneauxDisponibles as $slot)
                <button
                    type="button"
                    wire:click="$set('editHeure', '{{ $slot['heure'] }}')"
                    class="px-3 py-2 rounded-xl border text-sm
                            {{ $editHeure === $slot['heure'] ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-blue-50' }}">
                    {{ $slot['heure'] }}
                    <span class="block text-xs opacity-80">
                        {{ $slot['same_employee'] ? 'Même employé' : $slot['employe_name'] }}
                    </span>
                </button>
                @endforeach
            </div>
            @else
            <p class="text-sm text-red-600">
                Aucun créneau disponible pour cette date.
            </p>
            @endif
        </div>

        <div class="bg-white border rounded-xl p-4 text-sm text-gray-700 space-y-1">
            <p>
                💰 <span class="font-medium">Impact devis :</span>
                {{ $impactDevisMessage ?? 'Le devis sera recalculé si nécessaire.' }}
            </p>
            <p>
                👤 <span class="font-medium">Employé :</span>
                le système garde le même employé si possible, sinon il propose un autre employé disponible.
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <button
                wire:click="enregistrerModif"
                class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-blue-700">
                ✅ Confirmer la replanification
            </button>

            <button
                wire:click="fermerEdition"
                class="px-4 py-2 rounded-xl border text-sm text-gray-700 bg-white hover:bg-gray-50">
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

            <div class="flex flex-wrap gap-2 pt-2">
                @if($rdv->mission)
                <a href="{{ route('missions.tracking.live', $rdv->mission) }}"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    📍 Suivre la mission
                </a>
                @endif

                @if($rdv->mission)
                <a
                    href="{{ route('client.missions.tracking', $rdv->mission) }}"
                    class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                    Suivre mon employé
                </a>
                @endif

                @if($rdv->canStillBeEditedByClient())
                <button wire:click="modifier({{ $rdv->id }})"
                    class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    🔁 Replanifier
                </button>

                <button wire:click="demanderAnnulation({{ $rdv->id }})"
                    class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">
                    Annuler
                </button>
                @endif

                @if($rdv->recurring_series_id)
                <a href="{{ route('client.rendezvous.series.edit', $rdv->id) }}"
                    class="rounded-xl border px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">
                    🗓️ Gérer la série
                </a>
                @endif

                @if($rdv->status === 'termine' && $rdv->feedback)
                <span class="rounded-xl bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">
                    💬 Feedback laissé
                </span>
                @elseif($rdv->status === 'termine')
                <a href="{{ route('feedback.create', $rdv->id) }}"
                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    ⭐ Laisser un avis
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
                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:outline-none"></textarea>

            <div class="flex flex-wrap justify-end gap-3">
                <button type="button" wire:click="fermerAnnulation" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Retour</button>
                <button type="button" wire:click="confirmerAnnulation" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white">Confirmer l’annulation</button>
            </div>
        </div>
    </div>
    @endif

</x-page-shell>