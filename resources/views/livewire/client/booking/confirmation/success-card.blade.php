@if(session()->has('success'))
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-5">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700">Demande envoyée avec succès</p>
                <h3 class="mt-1 text-2xl font-bold text-emerald-900">Merci, votre réservation a bien été enregistrée.</h3>
                <p class="mt-2 text-sm text-emerald-800">Référence : <span class="font-bold">{{ $createdReference ?: '—' }}</span></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('client.rendezvous.index') }}" class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition">Voir mes rendez-vous</a>
                <a href="{{ route($bookingEntryRouteName) }}" class="inline-flex items-center rounded-xl border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 transition">Nouvelle demande</a>
            </div>
        </div>
    </div>
@endif
