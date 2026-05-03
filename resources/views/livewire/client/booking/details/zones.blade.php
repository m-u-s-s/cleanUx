<div>
    <label class="block text-sm font-semibold text-slate-700 mb-3">Zones à traiter</label>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        @foreach($zonesDisponibles as $key => $label)
            <label class="flex items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 hover:border-sky-200 hover:bg-sky-50/40 transition">
                <input type="checkbox" wire:model.live="zones_specifiques" value="{{ $key }}">
                <span class="text-sm text-slate-700">{{ $label }}</span>
            </label>
        @endforeach
    </div>
</div>
