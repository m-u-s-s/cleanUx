<div>
    <label class="block text-sm font-semibold text-slate-700 mb-2">Fréquence</label>
    <select wire:model.live="frequence" class="w-full rounded-2xl border-slate-300">
        <option value="">Choisir une fréquence</option>
        @foreach($frequences as $key => $label)
            <option value="{{ $key }}">{{ $label }}</option>
        @endforeach
    </select>
    @error('frequence') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
</div>
