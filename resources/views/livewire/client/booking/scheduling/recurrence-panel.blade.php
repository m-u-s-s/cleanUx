<div class="rounded-2xl border border-slate-200 p-4 space-y-3 bg-slate-50/40">
    <label class="flex items-center gap-3">
        <input type="checkbox" wire:model.live="is_recurrent">
        <span class="text-sm text-slate-700">Intervention récurrente</span>
    </label>

    @if($is_recurrent)
        @include('livewire.client.booking.scheduling.recurrence-fields')
    @endif

    <label class="flex items-center gap-3">
        <input type="checkbox" wire:model.live="is_favorite_slot">
        <span class="text-sm text-slate-700">Enregistrer ce créneau comme favori</span>
    </label>
</div>
