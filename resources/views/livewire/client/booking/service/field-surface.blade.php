<div>
    <label class="block text-sm font-semibold text-slate-700 mb-2">Surface</label>
    <select wire:model.live="surface" class="w-full rounded-2xl border-slate-300">
        <option value="">Choisir une surface</option>
        @foreach($surfaces as $key => $label)
            <option value="{{ $key }}">{{ $label }}</option>
        @endforeach
    </select>
    @error('surface') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
</div>
