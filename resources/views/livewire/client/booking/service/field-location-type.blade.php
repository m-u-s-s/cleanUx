<div>
    <label class="block text-sm font-semibold text-slate-700 mb-2">Type de lieu</label>
    <select wire:model.live="type_lieu" class="w-full rounded-2xl border-slate-300">
        <option value="">Choisir un type de lieu</option>
        @foreach($typesLieu as $key => $label)
            <option value="{{ $key }}">{{ $label }}</option>
        @endforeach
    </select>
    @error('type_lieu') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
</div>
