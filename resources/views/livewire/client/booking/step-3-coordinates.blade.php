<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block text-sm font-semibold text-slate-700 mb-2">Adresse</label>
        <input
            id="booking-address-autocomplete"
            type="text"
            wire:model.defer="adresse"
            class="w-full rounded-2xl border-slate-300"
            placeholder="Entrez votre adresse" />

        <input type="hidden" wire:model="google_place_id">
        <input type="hidden" wire:model="destination_lat">
        <input type="hidden" wire:model="destination_lng">
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



@push('scripts')
<script>
let cleanUxAutocompleteInitialized = false;

function initCleanUxAddressAutocomplete() {
    if (cleanUxAutocompleteInitialized) return;

    const input = document.getElementById('booking-address-autocomplete');

    if (!input || !window.google?.maps?.places) {
        return;
    }

    cleanUxAutocompleteInitialized = true;

    const autocomplete = new google.maps.places.Autocomplete(input, {
        fields: ['place_id', 'formatted_address', 'geometry', 'address_components'],
        componentRestrictions: { country: ['be'] },
        types: ['address'],
    });

    autocomplete.addListener('place_changed', function () {
        const place = autocomplete.getPlace();

        if (!place.geometry || !place.geometry.location) {
            return;
        }

        const lat = place.geometry.location.lat();
        const lng = place.geometry.location.lng();

        @this.set('adresse', place.formatted_address || input.value);
        @this.set('google_place_id', place.place_id || null);
        @this.set('destination_lat', lat);
        @this.set('destination_lng', lng);
        @this.set('address_components', place.address_components || []);

        const postal = (place.address_components || []).find(component =>
            component.types.includes('postal_code')
        );

        const city = (place.address_components || []).find(component =>
            component.types.includes('locality')
            || component.types.includes('postal_town')
            || component.types.includes('administrative_area_level_2')
        );

        if (postal) {
            @this.set('postal_code_input', postal.long_name);
            @this.set('code_postal', postal.long_name);
        }

        if (city) {
            @this.set('ville', city.long_name);
        }
    });
}

document.addEventListener('livewire:navigated', initCleanUxAddressAutocomplete);
document.addEventListener('DOMContentLoaded', initCleanUxAddressAutocomplete);
</script>

<script
    src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places&callback=initCleanUxAddressAutocomplete"
    async
    defer>
</script>
@endpush