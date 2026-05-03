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
