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
