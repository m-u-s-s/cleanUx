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

    @include('livewire.client.rendezvous.mission-tracking-panel', ['rdv' => $rdv])
    @include('livewire.client.rendezvous.actions', ['rdv' => $rdv])
</div>
