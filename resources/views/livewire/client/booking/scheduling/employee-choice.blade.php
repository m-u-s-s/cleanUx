@if($this->isPremiumClient())
    <div>
        <label class="block text-sm font-semibold text-slate-700 mb-2">Employé</label>
        <select wire:model.live="employe_id" class="w-full rounded-2xl border-slate-300">
            <option value="">Choisir un employé</option>
            @foreach($employesDisponibles as $employe)
                <option value="{{ $employe['id'] }}">{{ $employe['name'] }}</option>
            @endforeach
        </select>
        @error('employe_id') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
    </div>
@endif
