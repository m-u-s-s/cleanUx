<div class="sticky top-6 space-y-6">
    @if($this->hasPrefill)
    <div class="bg-amber-50 rounded-3xl shadow-sm border border-amber-200 p-6">
        <p class="text-sm font-medium text-amber-700">Modèle actif</p>
        <h3 class="text-lg font-bold text-amber-900 mt-1">Préremplissage détecté</h3>
        <div class="mt-4 space-y-2 text-sm text-amber-800">
            @if($prefilledFromSource)<p>🔁 Vous repartez d’une ancienne prestation.</p>@endif
            @if($prefilledFromLast)<p>⏱️ Vous repartez de votre dernière réservation.</p>@endif
            @if($prefilledFromAddress)<p>📍 Une adresse récente a été injectée dans le formulaire.</p>@endif
        </div>
    </div>
    @endif

    @if($step === 5 && $createdReference)
    <div class="bg-emerald-50 rounded-3xl shadow-sm border border-emerald-200 p-6">
        <p class="text-sm font-medium text-emerald-700">Suivi</p>
        <h3 class="text-lg font-bold text-emerald-900 mt-1">Demande enregistrée</h3>
        <div class="mt-4 space-y-2 text-sm text-emerald-800">
            <p>Référence : <span class="font-bold">{{ $createdReference }}</span></p>
            <p>Statut initial : <span class="font-semibold">en attente</span></p>
            @if($this->isPremiumClient() && $createdEmployeName)
            <p>Employé demandé : <span class="font-semibold">{{ $createdEmployeName }}</span></p>
            @endif
        </div>
    </div>
    @endif

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
        <p class="text-sm font-medium text-slate-500">Résumé de votre demande</p>
        <h3 class="text-xl font-bold text-slate-900 mt-1">Estimation en direct</h3>
        <div class="mt-6 space-y-4">
            <div class="rounded-2xl bg-slate-50 p-4 border border-slate-100">
                <p class="text-sm text-slate-500">Durée estimée</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ $duree_estimee > 0 ? $duree_estimee . ' min' : '--' }}</p>
            </div>
            <div class="rounded-2xl bg-sky-50 p-4 border border-sky-100">
                <p class="text-sm text-sky-700">Devis estimatif</p>
                <p class="text-3xl font-extrabold text-sky-900 mt-1">{{ number_format((float) $devis_estime, 2, ',', ' ') }} €</p>
                @if($promo_valid && $promo_discount_preview)
                    <p class="text-xs text-emerald-700 mt-2">
                        Avec code <span class="font-mono font-bold">{{ strtoupper($promo_code) }}</span> :
                        <span class="font-bold">-{{ number_format((float) $promo_discount_preview, 2, ',', ' ') }} €</span>
                    </p>
                @endif
            </div>

            <div class="rounded-2xl bg-white border border-slate-200 p-4">
                <label class="text-xs font-semibold uppercase text-slate-500">Code promo</label>
                <div class="flex gap-2 mt-2">
                    <input
                        type="text"
                        wire:model="promo_code"
                        class="flex-1 rounded-xl border-gray-300 text-sm uppercase"
                        placeholder="Ex: SUMMER25"
                    />
                    @if($promo_valid)
                        <button type="button"
                                wire:click="clearPromoCode"
                                class="rounded-xl bg-slate-100 px-3 py-1 text-xs font-semibold hover:bg-slate-200">
                            Retirer
                        </button>
                    @else
                        <button type="button"
                                wire:click="previewPromoCode"
                                class="rounded-xl bg-indigo-600 px-3 py-1 text-xs font-semibold text-white hover:bg-indigo-700">
                            Appliquer
                        </button>
                    @endif
                </div>
                @if($promo_message)
                    <p class="text-xs mt-2 {{ $promo_valid ? 'text-emerald-700' : 'text-red-600' }}">
                        {{ $promo_message }}
                    </p>
                @endif
                @error('promo_code')
                    <p class="text-xs mt-2 text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3"><span class="text-slate-500">Service</span><span class="font-semibold text-slate-800 text-right">{{ $selectedServiceLabel ?? ($services[$selected_service_identifier] ?? '—') }}</span></div>
                <div class="flex items-center justify-between gap-3"><span class="text-slate-500">Lieu</span><span class="font-semibold text-slate-800 text-right">{{ $typesLieu[$type_lieu] ?? '—' }}</span></div>
                <div class="flex items-center justify-between gap-3"><span class="text-slate-500">Fréquence</span><span class="font-semibold text-slate-800 text-right">{{ $frequences[$frequence] ?? '—' }}</span></div>
                <div class="flex items-center justify-between gap-3"><span class="text-slate-500">Surface</span><span class="font-semibold text-slate-800 text-right">{{ $surfaces[$surface] ?? '—' }}</span></div>
                <div class="flex items-center justify-between gap-3"><span class="text-slate-500">Options</span><span class="font-semibold text-slate-800 text-right">{{ count($options_prestation) }}</span></div>
                <div class="flex items-center justify-between gap-3"><span class="text-slate-500">Zones</span><span class="font-semibold text-slate-800 text-right">{{ count($zones_specifiques) }}</span></div>
            </div>
        </div>
    </div>

    @if(!$this->isPremiumClient())
    <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6">
        <p class="text-sm font-semibold text-amber-800">Passez en Premium</p>
        <p class="text-sm text-amber-700 mt-2">Choisissez vos employés favoris et consultez leurs disponibilités.</p>
    </div>
    @endif
</div>
