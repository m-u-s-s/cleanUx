@if(session()->has('info'))
<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
    {{ session('info') }}
</div>
@endif

@if(session()->has('booking_auth_required'))
<div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    {{ session('booking_auth_required') }}
</div>
@endif

@if($isGuestBooking)
<div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
    Vous pouvez préparer toute votre demande ici. La création de compte n’est demandée qu’au moment de la confirmation.
</div>
@endif

@if($this->isPremiumClient())
<div class="inline-flex items-center gap-2 rounded-full bg-amber-50 text-amber-700 px-4 py-2 text-sm font-semibold border border-amber-200">
    <span>★</span>
    <span>Client Premium actif</span>
</div>
@endif

@if($this->hasPrefill)
<div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-semibold text-amber-800">Formulaire prérempli</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @if($prefilledFromSource)
                <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-medium text-amber-700 border border-amber-200">🔁 Ancienne prestation reprise</span>
                @endif
                @if($prefilledFromLast)
                <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-medium text-amber-700 border border-amber-200">⏱️ Dernière réservation reprise</span>
                @endif
                @if($prefilledFromAddress)
                <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-medium text-amber-700 border border-amber-200">📍 Adresse réutilisée</span>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route($bookingEntryRouteName) }}" class="inline-flex items-center rounded-xl border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-100 transition">
                Repartir à zéro
            </a>
        </div>
    </div>
</div>
@endif
