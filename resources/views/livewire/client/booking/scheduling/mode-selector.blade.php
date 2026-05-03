<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <button
        type="button"
        wire:click="$set('booking_mode', 'asap')"
        class="rounded-2xl border px-4 py-4 text-left transition {{ $booking_mode === 'asap' ? 'border-emerald-500 bg-emerald-50 shadow-sm' : 'border-slate-200 bg-white hover:border-emerald-200 hover:bg-emerald-50/40' }}">
        <div class="font-bold text-slate-900">ASAP</div>
        <div class="text-sm text-slate-600">Un employé disponible sous 2h</div>
    </button>

    <button
        type="button"
        wire:click="$set('booking_mode', 'scheduled')"
        class="rounded-2xl border px-4 py-4 text-left transition {{ $booking_mode === 'scheduled' ? 'border-blue-500 bg-blue-50 shadow-sm' : 'border-slate-200 bg-white hover:border-blue-200 hover:bg-blue-50/40' }}">
        <div class="font-bold text-slate-900">Planifier</div>
        <div class="text-sm text-slate-600">Choisir une date et une heure</div>
    </button>
</div>
