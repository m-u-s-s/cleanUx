<div>
    <label class="block text-sm font-semibold text-slate-700 mb-2">Type de service</label>
    <select wire:model.live="selected_service_identifier" class="w-full rounded-2xl border-slate-300">
        <option value="">Choisir un service</option>
        @foreach($services as $key => $label)
            <option value="{{ $key }}">{{ $label }}</option>
        @endforeach
    </select>
    @error('selected_service_identifier') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
</div>
