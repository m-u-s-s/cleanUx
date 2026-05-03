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
