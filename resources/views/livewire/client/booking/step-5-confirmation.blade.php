<div class="space-y-6">
    @if($isGuestBooking)
    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-5 py-5 text-sm text-sky-800">
        Votre demande est prête. Il reste seulement la création de compte ou la connexion pour l’enregistrer définitivement et la suivre ensuite dans votre espace client.
    </div>
    @endif

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

    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
        <div class="flex items-center justify-between gap-3 mb-5">
            <div>
                <p class="text-sm text-slate-500">Résumé final</p>
                <h4 class="text-lg font-bold text-slate-900">Votre demande enregistrée</h4>
            </div>
            <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 border border-slate-200">Statut : en attente</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div><span class="text-slate-500">Service</span><p class="font-semibold">{{ $selectedServiceLabel ?? ($services[$selected_service_identifier] ?? '-') }}</p></div>
            <div><span class="text-slate-500">Lieu</span><p class="font-semibold">{{ $typesLieu[$type_lieu] ?? '-' }}</p></div>
            <div><span class="text-slate-500">Fréquence</span><p class="font-semibold">{{ $frequences[$frequence] ?? '-' }}</p></div>
            <div><span class="text-slate-500">Surface</span><p class="font-semibold">{{ $surfaces[$surface] ?? '-' }}</p></div>
            <div><span class="text-slate-500">Adresse</span><p class="font-semibold">{{ $adresse ?: '-' }}</p></div>
            <div><span class="text-slate-500">Ville</span><p class="font-semibold">{{ $ville ?: '-' }}</p></div>
            <div><span class="text-slate-500">Date</span><p class="font-semibold">{{ $rdvDate ?: '-' }}</p></div>
            <div><span class="text-slate-500">Heure</span><p class="font-semibold">{{ $rdvHeure ?: '-' }}</p></div>
            <div><span class="text-slate-500">Priorité</span><p class="font-semibold">{{ $priorites[$priorite] ?? '-' }}</p></div>
            <div><span class="text-slate-500">Téléphone</span><p class="font-semibold">{{ $telephone_client ?: '-' }}</p></div>
            @if($this->isPremiumClient())
            <div class="md:col-span-2"><span class="text-slate-500">Employé demandé</span><p class="font-semibold">{{ $createdEmployeName ?: 'Aucun employé spécifique' }}</p></div>
            @endif
        </div>
    </div>
</div>
