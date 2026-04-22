<div class="border rounded-xl p-4 bg-gray-50 shadow-sm space-y-4 {{ $rdv->priorite === 'urgente' ? 'ring-2 ring-red-200 border-red-300' : '' }}">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h4 class="font-semibold text-gray-800 text-lg">
                {{ $rdv->service_display_name }}
            </h4>

            @if($rdv->client)
            <p class="text-sm text-gray-600">👤 {{ $rdv->client->name }}</p>
            @endif

            @if($rdv->employe)
            <p class="text-sm text-gray-600">🧑‍💼 {{ $rdv->employe->name }}</p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-badge :status="$rdv->status" />
            <x-priority-badge :priority="$rdv->priorite" />
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
        <div class="space-y-1">
            <p><span class="font-medium">Date :</span> {{ $rdv->date }} à {{ $rdv->heure }}</p>
            <p><span class="font-medium">Type de lieu :</span> {{ ucfirst($rdv->type_lieu ?? '—') }}</p>
            <p><span class="font-medium">Fréquence :</span> {{ ucfirst(str_replace('_', ' ', $rdv->frequence ?? '—')) }}</p>
            <p><span class="font-medium">Surface :</span> {{ $rdv->surface ?? '—' }}</p>
        </div>

        <div class="space-y-1">
            <p><span class="font-medium">Adresse :</span> {{ $rdv->adresse ?? '—' }}</p>
            <p><span class="font-medium">Ville :</span> {{ $rdv->ville ?? '—' }}</p>
            <p><span class="font-medium">Code postal :</span> {{ $rdv->postal_code_display }}</p>
            <p><span class="font-medium">Téléphone :</span> {{ $rdv->telephone_client ?? '—' }}</p>
            <p><span class="font-medium">Animaux :</span> {{ $rdv->presence_animaux ? 'Oui' : 'Non' }}</p>
            <p><span class="font-medium">Parking :</span> {{ $rdv->acces_parking ? 'Oui' : 'Non' }}</p>
            <p><span class="font-medium">Matériel fourni :</span> {{ $rdv->materiel_fournit ? 'Oui' : 'Non' }}</p>
            <p><span class="font-medium">Durée estimée :</span> {{ $rdv->duree_estimee ? $rdv->duree_estimee . ' min' : '—' }}</p>
        </div>
    </div>

    @if($rdv->commentaire_client)
    <div class="text-sm text-gray-700 bg-white border rounded-lg p-3">
        <span class="font-medium">Remarque client :</span>
        {{ $rdv->commentaire_client }}
    </div>
    @endif


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

    @if($rdv->commentaire_fin_mission)
    <div class="text-sm text-gray-700 bg-emerald-50 border border-emerald-200 rounded-lg p-3">
        <span class="font-medium">Rapport de fin :</span>
        {{ $rdv->commentaire_fin_mission }}
    </div>
    @endif

    @if($rdv->duree_reelle)
    <div class="text-sm text-gray-700">
        <span class="font-medium">Durée réelle :</span> {{ $rdv->duree_reelle }} min
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
    {{ $slot }}
</div>