<div>
    <label class="block text-sm font-semibold text-slate-700 mb-2">Type de service</label>
    <select wire:model.live="selected_service_identifier" class="w-full rounded-2xl border-slate-300">
        <option value="">Choisir un service</option>

        {{-- Phase F4 — $groupedServices is pre-filtered by trade when a trade is selected,
             otherwise falls back to the full $servicesGroupedByTrade from the component.
             If neither is available, falls back to the flat $services map. --}}
        @php
            $displayGroups = $groupedServices ?? $servicesGroupedByTrade ?? [];
        @endphp

        @if(!empty($displayGroups))
            @foreach($displayGroups as $tradeName => $tradeServices)
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
