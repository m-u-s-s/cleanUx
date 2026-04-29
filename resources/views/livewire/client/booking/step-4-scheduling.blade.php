<div class="space-y-6">
    @if($this->isPremiumClient())
    <div>
        <label class="block text-sm font-semibold text-slate-700 mb-2">Employé</label>
        <select wire:model.live="employe_id" class="w-full rounded-2xl border-slate-300">
            <option value="">Choisir un employé</option>
            @foreach($employesDisponibles as $employe)
            <option value="{{ $employe['id'] }}">{{ $employe['name'] }}</option>
            @endforeach
        </select>
        @error('employe_id') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
    </div>
    @endif

    <!-- mode asap -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <button
            type="button"
            wire:click="$set('booking_mode', 'asap')"
            class="rounded-2xl border px-4 py-4 text-left {{ $booking_mode === 'asap' ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 bg-white' }}">
            <div class="font-bold text-slate-900">ASAP</div>
            <div class="text-sm text-slate-600">Un employé disponible sous 2h</div>
        </button>

        <button
            type="button"
            wire:click="$set('booking_mode', 'scheduled')"
            class="rounded-2xl border px-4 py-4 text-left {{ $booking_mode === 'scheduled' ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-white' }}">
            <div class="font-bold text-slate-900">Planifier</div>
            <div class="text-sm text-slate-600">Choisir une date et une heure</div>
        </button>
    </div>

    @if($asapMessage)
    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        {{ $asapMessage }}
    </div>
    @endif


    <!-- mode reservation -->
    @if($booking_mode !== 'asap')
    {{
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Date souhaitée</label>
            <input type="date" wire:model.live="rdvDate" min="{{ now()->toDateString() }}" class="w-full rounded-2xl border-slate-300">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Heure souhaitée</label>
            <select wire:model.defer="rdvHeure" class="w-full rounded-2xl border-slate-300">
                <option value="">Choisir un créneau</option>
                    @foreach($creneauxDisponibles as $creneau)
                        <option value="{{ $creneau }}">{{ $creneau }}</option>
                    @endforeach
            </select>
        </div>
    </div>}}
    @endif
    
<div class="rounded-2xl border border-slate-200 p-4 space-y-3">
    <label class="flex items-center gap-3">
        <input type="checkbox" wire:model.live="is_recurrent">
        <span class="text-sm text-slate-700">Intervention récurrente</span>
    </label>

    @if($is_recurrent)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Fréquence</label>
            <select wire:model.live="recurrence_frequency" class="w-full rounded-2xl border-slate-300">
                <option value="">Choisir une fréquence</option>
                @foreach($recurringFrequencyOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('recurrence_frequency') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Intervalle</label>
            <input type="number" min="1" max="12" wire:model.live="recurrence_interval" class="w-full rounded-2xl border-slate-300">
            @error('recurrence_interval') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Jusqu’au</label>
            <input type="date" wire:model.live="recurrence_until" class="w-full rounded-2xl border-slate-300">
            @error('recurrence_until') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Nombre d’occurrences</label>
            <input type="number" min="2" max="52" wire:model.live="recurrence_count" class="w-full rounded-2xl border-slate-300" placeholder="Ex. 4">
            @error('recurrence_count') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
        </div>
    </div>
    @if($recurrence_frequency === 'weekly')
    <div>
        <label class="block text-sm font-semibold text-slate-700 mb-2">Jours de passage</label>
        <div class="flex flex-wrap gap-2">
            @foreach($recurringDayOptions as $dayValue => $dayLabel)
            <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                <input type="checkbox" value="{{ $dayValue }}" wire:model.live="recurrence_days">
                <span>{{ $dayLabel }}</span>
            </label>
            @endforeach
        </div>
        @error('recurrence_days') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
    </div>
    @endif
    <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-800">
        La version de démarrage crée la série complète et vérifie les disponibilités. La modification d’une occurrence seule arrivera dans le prochain patch.
    </div>
    @endif

    <label class="flex items-center gap-3">
        <input type="checkbox" wire:model.live="is_favorite_slot">
        <span class="text-sm text-slate-700">Enregistrer ce créneau comme favori</span>
    </label>
</div>
</div>