<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
    </div>
</div>
