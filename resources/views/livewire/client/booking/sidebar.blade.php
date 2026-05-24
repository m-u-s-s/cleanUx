<div class="sticky top-6 space-y-4">
    @if($this->hasPrefill)
        <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5 shadow-soft-sm">
            <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-amber-700">
                <x-ui.icon name="sparkles" class="w-3.5 h-3.5" />
                Modèle actif
            </p>
            <h3 class="mt-2 text-sm font-bold text-amber-900">Préremplissage détecté</h3>
            <ul class="mt-3 space-y-1.5 text-xs text-amber-800">
                @if($prefilledFromSource)
                    <li class="inline-flex items-center gap-1.5"><x-ui.icon name="refresh" class="w-3 h-3" /> Repris d'une ancienne prestation</li>
                @endif
                @if($prefilledFromLast)
                    <li class="inline-flex items-center gap-1.5"><x-ui.icon name="history" class="w-3 h-3" /> Repris de votre dernière réservation</li>
                @endif
                @if($prefilledFromAddress)
                    <li class="inline-flex items-center gap-1.5"><x-ui.icon name="map-pin" class="w-3 h-3" /> Adresse récente injectée</li>
                @endif
            </ul>
        </div>
    @endif

    @if($step === 5 && $createdReference)
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-soft-sm">
            <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                <x-ui.icon name="check-circle" class="w-3.5 h-3.5" />
                Suivi
            </p>
            <h3 class="mt-2 text-sm font-bold text-emerald-900">Demande enregistrée</h3>
            <div class="mt-3 space-y-1.5 text-xs text-emerald-800">
                <p>Référence : <span class="font-mono font-bold">{{ $createdReference }}</span></p>
                <p>Statut initial : <span class="font-semibold">en attente</span></p>
                @if($this->isPremiumClient() && $createdEmployeName)
                    <p>Employé demandé : <span class="font-semibold">{{ $createdEmployeName }}</span></p>
                @endif
            </div>
        </div>
    @endif

    {{-- Estimation en direct --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-soft p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Résumé</p>
        <h3 class="mt-1 text-base font-bold tracking-tight text-slate-900">Estimation en direct</h3>

        <div class="mt-5 space-y-3">
            <div class="rounded-xl bg-slate-50/50 border border-slate-200/70 p-3.5">
                <p class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                    <x-ui.icon name="clock" class="w-3 h-3" />
                    Durée estimée
                </p>
                <p class="mt-1 text-lg font-bold text-slate-900">{{ $duree_estimee > 0 ? $duree_estimee . ' min' : '—' }}</p>
            </div>

            <div class="rounded-xl bg-gradient-to-br from-brand-50 to-white border border-brand-100 p-3.5">
                <p class="inline-flex items-center gap-1.5 text-xs text-brand-700 font-semibold">
                    <x-ui.icon name="currency-euro" class="w-3 h-3" />
                    Devis estimatif
                </p>
                <p class="mt-1 text-2xl font-bold text-brand-700">{{ number_format((float) $devis_estime, 2, ',', ' ') }} €</p>
                @if($promo_valid && $promo_discount_preview)
                    <p class="mt-1.5 inline-flex items-center gap-1 text-[11px] text-emerald-700 font-medium">
                        <x-ui.icon name="tag" class="w-3 h-3" />
                        Avec code <span class="font-mono font-bold">{{ strtoupper($promo_code) }}</span>
                        <span class="ml-1">−{{ number_format((float) $promo_discount_preview, 2, ',', ' ') }} €</span>
                    </p>
                @endif
            </div>

            {{-- Code promo --}}
            <div class="rounded-xl border border-slate-200/70 bg-white p-3.5">
                <label class="ui-label !text-[11px] inline-flex items-center gap-1">
                    <x-ui.icon name="tag" class="w-3 h-3 text-slate-400" />
                    Code promo
                </label>
                <div class="flex gap-1.5 mt-1.5">
                    <input type="text" wire:model="promo_code"
                           class="ui-input flex-1 uppercase text-sm"
                           placeholder="Ex: SUMMER25" />
                    @if($promo_valid)
                        <button type="button" wire:click="clearPromoCode"
                                class="rounded-lg bg-slate-100 hover:bg-slate-200 px-3 text-xs font-semibold text-slate-700 transition">
                            Retirer
                        </button>
                    @else
                        <button type="button" wire:click="previewPromoCode"
                                class="rounded-lg bg-brand-600 hover:bg-brand-700 px-3 text-xs font-semibold text-white transition">
                            Appliquer
                        </button>
                    @endif
                </div>
                @if($promo_message)
                    <p class="mt-1.5 text-xs inline-flex items-start gap-1 {{ $promo_valid ? 'text-emerald-700' : 'text-rose-600' }}">
                        <x-ui.icon name="{{ $promo_valid ? 'check-circle' : 'exclamation-triangle' }}" class="w-3 h-3 mt-0.5 flex-shrink-0" />
                        <span>{{ $promo_message }}</span>
                    </p>
                @endif
                @error('promo_code')
                    <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Récap inline --}}
            <dl class="space-y-1.5 text-xs">
                @foreach([
                    ['Service',   $selectedServiceLabel ?? ($services[$selected_service_identifier] ?? '—')],
                    ['Lieu',      $typesLieu[$type_lieu] ?? '—'],
                    ['Fréquence', $frequences[$frequence] ?? '—'],
                    ['Surface',   $surfaces[$surface] ?? '—'],
                    ['Options',   count($options_prestation)],
                    ['Zones',     count($zones_specifiques)],
                ] as [$label, $value])
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">{{ $label }}</dt>
                        <dd class="font-semibold text-slate-800 text-right truncate">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    @if(!$this->isPremiumClient())
        <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-5 shadow-soft-sm">
            <div class="flex items-start gap-2">
                <div class="grid h-8 w-8 place-items-center rounded-lg bg-amber-500 text-white shrink-0">
                    <x-ui.icon name="star" class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-amber-900">Passez en Premium</p>
                    <p class="mt-0.5 text-xs text-amber-700">
                        Choisissez vos employés favoris et consultez leurs disponibilités.
                    </p>
                    @if(Route::has('premium.offer'))
                        <a href="{{ route('premium.offer') }}"
                           class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-amber-800 hover:text-amber-900">
                            En savoir plus
                            <x-ui.icon name="arrow-right" class="w-3 h-3" />
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
