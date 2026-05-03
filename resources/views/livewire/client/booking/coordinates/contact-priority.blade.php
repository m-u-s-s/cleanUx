<div>
    <label class="block text-sm font-semibold text-slate-700 mb-2">Téléphone</label>
    <input type="text" wire:model.defer="telephone_client" class="w-full rounded-2xl border-slate-300">
</div>

<div>
    <label class="block text-sm font-semibold text-slate-700 mb-2">Priorité</label>
    <select wire:model.defer="priorite" class="w-full rounded-2xl border-slate-300">
        @foreach($priorites as $key => $label)
            <option value="{{ $key }}">{{ $label }}</option>
        @endforeach
    </select>
</div>
