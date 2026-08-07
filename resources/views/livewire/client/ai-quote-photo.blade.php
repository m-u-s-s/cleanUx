<div class="py-8 max-w-3xl mx-auto px-4">

    <div class="text-center mb-6">
        <div class="grid h-12 w-12 mx-auto place-items-center rounded-2xl bg-brand-50 text-brand-600 mb-3">
            <x-ui.icon name="sparkles" class="w-6 h-6" />
        </div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Devis IA depuis photo</h1>
        <p class="mt-1 text-sm text-slate-500">Prenez une photo, choisissez le métier, recevez un devis estimé en quelques secondes.</p>
    </div>

    @if (! $result)
        <div class="rounded-2xl bg-white border border-slate-200/80 shadow-soft p-6 sm:p-8">
            <div class="space-y-5">
                {{-- Métier --}}
                <div>
                    <label class="ui-label">Métier *</label>
                    <select wire:model="tradeId" class="ui-input">
                        <option value="">— Choisir un métier —</option>
                        @foreach ($trades as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                    @error('tradeId') <p class="ui-error-msg">{{ $message }}</p> @enderror
                </div>

                {{-- Photo --}}
                <div>
                    <label class="ui-label inline-flex items-center gap-2">
                        <x-ui.icon name="camera" class="w-4 h-4 text-slate-500" />
                        Photo de la prestation *
                    </label>
                    <input wire:model="photo" type="file" accept="image/*"
                           class="w-full text-sm mt-1.5 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:px-3 file:py-2 file:font-semibold file:hover:bg-brand-100 file:transition file:mr-3">
                    @error('photo') <p class="ui-error-msg">{{ $message }}</p> @enderror
                    <p class="text-xs text-slate-500 mt-1.5 inline-flex items-start gap-1">
                        <x-ui.icon name="information-circle" class="w-3.5 h-3.5 mt-0.5 flex-shrink-0" />
                        PNG / JPG / WEBP, max 5 MB. Conseil : éclairage clair, plan large.
                    </p>
                </div>

                @if ($photo)
                    <div class="rounded-xl overflow-hidden border border-slate-200">
                        <img src="{{ $photo->temporaryUrl() }}" alt="Aperçu" class="w-full max-h-96 object-contain bg-slate-50">
                    </div>
                @endif

                {{-- Note --}}
                <div>
                    <label class="ui-label">Note (optionnel)</label>
                    <textarea wire:model="note" rows="3" maxlength="1000"
                              placeholder="Précisions, surface, état actuel, contraintes…"
                              class="ui-input"></textarea>
                </div>

                @if ($error)
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-3.5 text-sm text-rose-700 inline-flex items-start gap-2">
                        <x-ui.icon name="exclamation-triangle" class="w-4 h-4 mt-0.5 flex-shrink-0" />
                        <span>{{ $error }}</span>
                    </div>
                @endif

                <button wire:click="estimate" wire:loading.attr="disabled" @disabled($estimating)
                        class="w-full rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white py-3 text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed transition inline-flex items-center justify-center gap-2">
                    <span wire:loading.remove wire:target="estimate" class="inline-flex items-center gap-2">
                        <x-ui.icon name="sparkles" class="w-4 h-4" />
                        Estimer mon devis
                    </span>
                    <span wire:loading wire:target="estimate" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        Analyse en cours…
                    </span>
                </button>
                <p class="text-xs text-slate-500 text-center">L'IA analyse votre photo en ~10 secondes. Devis indicatif (±15-20%).</p>
            </div>
        </div>
    @else
        {{-- Résultat estimation --}}
        <div class="rounded-2xl bg-white border border-slate-200/80 shadow-soft p-6 sm:p-8">
            <div class="text-center mb-6">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-bold uppercase tracking-wide text-emerald-700">
                    <x-ui.icon name="check-circle" class="w-3.5 h-3.5" />
                    Devis estimé
                </span>
                <h2 class="text-2xl font-bold tracking-tight text-slate-900 mt-3">{{ $result['trade_name'] ?? '' }}</h2>
            </div>

            <div class="rounded-2xl border border-brand-100 bg-gradient-to-br from-brand-50 to-white p-6 text-center mb-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide font-semibold">Fourchette de prix</p>
                <p class="mt-2 text-3xl font-bold text-brand-700">
                    {{ number_format($result['prix_min_eur'] ?? 0, 0, ',', ' ') }}€
                    <span class="text-slate-400">–</span>
                    {{ number_format($result['prix_max_eur'] ?? 0, 0, ',', ' ') }}€
                </p>
                <p class="text-xs text-slate-500 mt-3 inline-flex items-center gap-3">
                    <span class="inline-flex items-center gap-1">
                        <x-ui.icon name="clock" class="w-3.5 h-3.5" />
                        <strong>{{ round(($result['duree_estimee_min'] ?? 0) / 60, 1) }}h</strong>
                    </span>
                    @if (!empty($result['surface_estimee_m2']))
                        <span class="text-slate-300">·</span>
                        <span class="inline-flex items-center gap-1">
                            Surface <strong>{{ $result['surface_estimee_m2'] }} m²</strong>
                        </span>
                    @endif
                </p>
            </div>

            @if (!empty($result['confiance']))
                @php
                    $conf = (int) $result['confiance'];
                    $confTone = $conf >= 70 ? 'emerald' : ($conf >= 50 ? 'amber' : 'rose');
                @endphp
                <div class="mb-4">
                    <div class="flex justify-between text-xs mb-1.5">
                        <span class="text-slate-600 font-medium">Confiance IA</span>
                        <span class="font-bold text-{{ $confTone }}-600">{{ $conf }}%</span>
                    </div>
                    <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                        <div class="bg-{{ $confTone }}-500 h-2 rounded-full transition-all" style="width: {{ $conf }}%"></div>
                    </div>
                </div>
            @endif

            {{-- Audit LOW — disclaimer légal : l'estimation IA n'est pas un devis ferme. --}}
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 flex items-start gap-2">
                <span aria-hidden="true">⚠️</span>
                <span>
                    <strong>Estimation indicative</strong> générée par IA à partir de votre photo.
                    Elle ne constitue <strong>pas un devis ferme</strong> : le prix définitif est confirmé
                    par le prestataire après échange/visite, selon l'état réel et les contraintes du chantier.
                </span>
            </div>

            @if (!empty($result['etat_observe']))
                <div class="mb-3">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">État observé</p>
                    <p class="text-sm text-slate-700 mt-1">{{ $result['etat_observe'] }}</p>
                </div>
            @endif

            @if (!empty($result['observations']))
                <div class="mb-3 rounded-xl bg-slate-50 border border-slate-200/70 p-3.5 text-sm text-slate-700 inline-flex items-start gap-2 w-full">
                    <x-ui.icon name="chat-bubble" class="w-4 h-4 mt-0.5 text-slate-400 flex-shrink-0" />
                    <span>{{ $result['observations'] }}</span>
                </div>
            @endif

            @if (!empty($result['options_suggerees']))
                <div class="mb-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Options suggérées</p>
                    <ul class="space-y-1.5">
                        @foreach ($result['options_suggerees'] as $opt)
                            <li class="text-sm text-slate-700 inline-flex items-start gap-2 w-full">
                                <x-ui.icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 flex-shrink-0" />
                                <span>{{ $opt }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-xl bg-amber-50 border border-amber-200 p-3.5 text-xs text-amber-800 mb-5 inline-flex items-start gap-2 w-full">
                <x-ui.icon name="exclamation-triangle" class="w-4 h-4 mt-0.5 flex-shrink-0" />
                <span>Devis indicatif basé sur analyse photo. Le prix final sera confirmé par le prestataire après visite ou échange.</span>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <button wire:click="reset_" class="brio-btn-secondary inline-flex items-center justify-center gap-2">
                    <x-ui.icon name="refresh" class="w-4 h-4" />
                    Refaire
                </button>
                <a href="{{ Route::has('client.rendezvous.create') ? route('client.rendezvous.create') : '/dashboard/client/rendez-vous/nouveau' }}"
                   class="brio-btn-primary inline-flex items-center justify-center gap-2">
                    <span>Réserver</span>
                    <x-ui.icon name="arrow-right" class="w-4 h-4" />
                </a>
            </div>
        </div>
    @endif
</div>
