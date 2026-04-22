<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block text-sm font-semibold text-slate-700 mb-2">Adresse</label>
        <input type="text" wire:model.defer="adresse" class="w-full rounded-2xl border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-semibold text-slate-700 mb-2">Ville</label>
        <input type="text" wire:model.defer="ville" class="w-full rounded-2xl border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-semibold text-slate-700 mb-2">Code postal</label>
        <input type="text" wire:model.defer="postal_code_input" class="w-full rounded-2xl border-slate-300">
    </div>
    @if($coverageMessage)
    <div class="md:col-span-2">
        <div class="rounded-2xl border px-4 py-3 text-sm {{ $coverageStatus === 'covered' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
            {{ $coverageMessage }}
        </div>
    </div>
    @endif
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
</div>
