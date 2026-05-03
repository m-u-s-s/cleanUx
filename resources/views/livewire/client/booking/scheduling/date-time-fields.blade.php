@if($booking_mode !== 'asap')
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
    </div>
@endif
