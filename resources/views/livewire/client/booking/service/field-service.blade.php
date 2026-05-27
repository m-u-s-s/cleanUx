<div>
    <label class="block text-sm font-semibold text-slate-700 mb-2">Type de service</label>
    <select wire:model.live="selected_service_identifier" class="w-full rounded-2xl border-slate-300">
        <option value="">Choisir un service</option>

        {{-- Phase 1 multi-métiers — services groupés par Trade via <optgroup>.
             Si la liste $servicesGroupedByTrade n'est pas dispo (ex. fallback
             pour un appelant qui n'a pas hydraté la nouvelle propriété), on
             retombe sur le rendu flat existant. --}}
        @if(isset($servicesGroupedByTrade) && !empty($servicesGroupedByTrade))
            @foreach($servicesGroupedByTrade as $tradeName => $tradeServices)
                <optgroup label="{{ $tradeName }}">
                    @foreach($tradeServices as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        @else
            @foreach($services as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        @endif
    </select>
    @error('selected_service_identifier') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
</div>
