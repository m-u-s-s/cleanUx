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
