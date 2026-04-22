<x-page-shell
    title="🕘 Historique"
    subtitle="Retrouvez vos missions terminées, rapports de fin, photos après intervention et feedbacks.">
    <x-slot name="actions">
        <a
            href="{{ route('client.rendezvous.create', ['prefill' => 'last']) }}"
            class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
            ➕ Reprendre le dernier type de prestation
        </a>
    </x-slot>

    <div class="bg-white rounded-2xl shadow border p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                <input
                    type="text"
                    wire:model.live="search"
                    placeholder="Service, ville, adresse..."
                    class="w-full border-gray-300 rounded-lg shadow-sm">
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

    <div class="space-y-4">
        @forelse($historique as $rdv)
        <div class="border rounded-2xl p-4 bg-gray-50 text-sm text-gray-700 space-y-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                <div>
                    <p class="font-medium text-gray-800 text-lg">
                        {{ $rdv->service_display_name }}
                    </p>
                    <p>{{ $rdv->date }} à {{ $rdv->heure }}</p>
                    <p>🧑‍💼 {{ $rdv->employe->name ?? '—' }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <x-badge :status="$rdv->status" />
                    <x-priority-badge :priority="$rdv->priorite" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <p><span class="font-medium">Adresse :</span> {{ $rdv->adresse ?? '—' }}, {{ $rdv->ville ?? '—' }}</p>
                    <p><span class="font-medium">Type de lieu :</span> {{ ucfirst($rdv->type_lieu ?? '—') }}</p>
                    <p><span class="font-medium">Durée estimée :</span> {{ $rdv->duree_estimee ? $rdv->duree_estimee . ' min' : '—' }}</p>
                    <p><span class="font-medium">Durée réelle :</span> {{ $rdv->duree_reelle ? $rdv->duree_reelle . ' min' : '—' }}</p>
                </div>

                <div>
                    <p><span class="font-medium">Fréquence :</span> {{ ucfirst(str_replace('_', ' ', $rdv->frequence ?? '—')) }}</p>
                    <p><span class="font-medium">Surface :</span> {{ $rdv->surface ?? '—' }}</p>
                </div>
            </div>

            @if($rdv->commentaire_fin_mission)
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3">
                <span class="font-medium text-emerald-800">Rapport de fin d’intervention :</span>
                <p class="mt-1 text-emerald-900">{{ $rdv->commentaire_fin_mission }}</p>
            </div>
            @endif

            @if($rdv->feedback)
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3">
                <span class="font-medium text-amber-800">Votre feedback :</span>
                <p class="mt-1">Note : {{ $rdv->feedback->note ?? '—' }}/5</p>
                <p>{{ $rdv->feedback->commentaire ?? 'Aucun commentaire.' }}</p>

                @if($rdv->feedback->reponse_admin)
                <div class="mt-2 pt-2 border-t border-amber-200">
                    <span class="font-medium text-amber-800">Réponse admin :</span>
                    <p>{{ $rdv->feedback->reponse_admin }}</p>
                </div>
                @endif
            </div>
            @endif

            <div class="flex flex-wrap gap-3 text-sm">
                <a href="{{ route('client.rendezvous.create', ['source_rdv' => $rdv->id]) }}" class="text-slate-700 underline">
                    🔁 Reprendre cette prestation
                </a>

                @if(!$rdv->feedback)
                <a href="{{ route('feedback.create', $rdv->id) }}" class="text-blue-600 underline">
                    💬 Laisser un feedback
                </a>
                @endif
            </div>

            @if(!empty($rdv->photos_reference))
            <div class="space-y-2">
                <p class="text-sm font-medium text-gray-700">📷 Photos de référence</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach($rdv->photos_reference as $photo)
                    <a href="{{ asset('storage/' . $photo) }}" target="_blank" class="block">
                        <img
                            src="{{ asset('storage/' . $photo) }}"
                            alt="Photo de référence"
                            class="w-full h-28 object-cover rounded-lg border hover:opacity-90 transition">
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            @if(!empty($rdv->photos_apres))
            <div class="space-y-2">
                <p class="text-sm font-medium text-gray-700">📸 Photos après intervention</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach($rdv->photos_apres as $photo)
                    <a href="{{ asset('storage/' . $photo) }}" target="_blank" class="block">
                        <img
                            src="{{ asset('storage/' . $photo) }}"
                            alt="Photo après intervention"
                            class="w-full h-28 object-cover rounded-lg border hover:opacity-90 transition">
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @empty
        <x-empty-state
            title="Aucune mission terminée"
            message="Votre historique apparaîtra ici après vos premières interventions terminées." />
        @endforelse
    </div>

    <div class="mt-4">
        {{ $historique->links() }}
    </div>
</x-page-shell>