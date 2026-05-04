    <div class="rounded-2xl border bg-white p-5 shadow-sm space-y-4">
        <h3 class="font-semibold text-slate-900">Générer une facture mensuelle</h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="text-sm font-medium text-slate-700">Entreprise</label>
                <select wire:model="organization_account_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="">— Choisir —</option>
                    @foreach($organizations as $organization)
                        <option value="{{ $organization->id }}">
                            {{ $organization->name }}
                        </option>
                    @endforeach
                </select>
                @error('organization_account_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Début période</label>
                <input type="date" wire:model="period_start" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                @error('period_start') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Fin période</label>
                <input type="date" wire:model="period_end" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                @error('period_end') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <button
            type="button"
            wire:click="generate"
            class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            Générer la facture groupée
        </button>
    </div>
