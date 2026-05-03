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
    La série complète est créée avec vérification des disponibilités. Les occurrences restent modifiables depuis votre espace client.
</div>
