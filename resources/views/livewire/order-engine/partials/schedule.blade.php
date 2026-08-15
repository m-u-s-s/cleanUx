{{--
    Date, créneau, et professionnel — dans cet ordre.

    Un créneau indisponible est GRISÉ et EXPLIQUÉ, jamais masqué. Retiré, il laisserait une grille
    trouée que le client lirait comme une panne du service ; grisé avec sa raison, il informe et
    rend lisibles ceux qui restent.

    Le choix du professionnel arrive en dernier et reste facultatif : « le meilleur disponible »
    est déjà retenu et suffit pour continuer.
--}}
<section class="rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="creneau-titre">
    <h2 id="creneau-titre" class="text-lg font-semibold text-slate-900">Quand ?</h2>

    {{-- ─── Le jour ─────────────────────────────────────────────────────────────────────── --}}
    <div class="mt-4 flex snap-x snap-mandatory gap-2 overflow-x-auto pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        role="radiogroup" aria-label="Jour de l’intervention">
        @foreach ($this->dayOptions as $day)
            @php $iso = $day->toDateString(); @endphp
            <button type="button" wire:click="selectDate('{{ $iso }}')"
                role="radio" aria-checked="{{ $selectedDate === $iso ? 'true' : 'false' }}"
                @class([
                    'flex min-h-[68px] w-[68px] shrink-0 snap-start flex-col items-center justify-center rounded-xl border transition',
                    'border-slate-900 bg-slate-900 text-white' => $selectedDate === $iso,
                    'border-slate-200 bg-white text-slate-700 hover:border-slate-300' => $selectedDate !== $iso,
                ])>
                <span class="text-[11px] uppercase tracking-wide opacity-70">{{ $day->translatedFormat('D') }}</span>
                <span class="text-lg font-semibold tabular-nums leading-tight">{{ $day->format('j') }}</span>
                <span class="text-[11px] opacity-70">{{ $day->translatedFormat('M') }}</span>
            </button>
        @endforeach
    </div>

    {{-- ─── Les créneaux ────────────────────────────────────────────────────────────────── --}}
    @if ($selectedDate)
        @php $slots = $this->slots; @endphp

        @if (! count($slots))
            {{--
                LE MESSAGE NOMME LE VRAI OBSTACLE.

                Il disait « indiquez d'abord l'adresse » dans tous les cas — y compris à quelqu'un
                qui venait de choisir son adresse enregistrée et dont la zone était résolue. On
                reprochait au client ce qu'il venait de faire, sans autre issue que la retaper.

                Il n'y a pas de créneau pour deux raisons distinctes, et elles n'appellent pas le
                même geste : soit on ne sait pas OÙ, soit on sait où mais on n'a pas su PLACER
                l'adresse sur la carte.
            --}}
            <p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                @if (blank($address))
                    Indiquez d’abord l’adresse : les créneaux dépendent des professionnels qui couvrent votre zone.
                @else
                    Nous n’avons pas réussi à situer cette adresse sur la carte. Précisez-la — le
                    code postal et la commune suffisent — pour que nous puissions chercher les
                    professionnels autour.
                @endif
            </p>
        @else
            <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-4" role="radiogroup" aria-label="Créneau">
                @foreach ($slots as $slot)
                    @php $time = $slot['start']->format('H:i'); @endphp
                    <button type="button"
                        @if ($slot['available']) wire:click="selectSlot('{{ $time }}')" @endif
                        @disabled(! $slot['available'])
                        role="radio" aria-checked="{{ $selectedSlot === $time ? 'true' : 'false' }}"
                        {{-- La raison est rattachée au bouton : un lecteur d'écran doit l'entendre,
                             pas seulement la deviner d'un gris. --}}
                        @if ($slot['reason']) title="{{ $slot['reason'] }}" aria-label="{{ $time }} — {{ $slot['reason'] }}" @endif
                        @class([
                            'min-h-[48px] rounded-xl border text-sm font-medium tabular-nums transition',
                            'border-slate-900 bg-slate-900 text-white' => $selectedSlot === $time,
                            'border-slate-200 bg-white text-slate-800 hover:border-slate-400' => $slot['available'] && $selectedSlot !== $time,
                            'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-400' => ! $slot['available'],
                        ])>
                        {{ $time }}
                    </button>
                @endforeach
            </div>

            @php $unavailable = collect($slots)->firstWhere('available', false); @endphp
            @if ($unavailable && ! collect($slots)->contains('available', true))
                {{-- Journée entièrement fermée : on dit pourquoi, et on ne laisse pas sans suite. --}}
                <p class="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {{ $unavailable['reason'] }} Essayez un autre jour — les créneaux se libèrent souvent la veille.
                </p>
            @endif
        @endif
    @endif

    {{-- ─── Le professionnel ────────────────────────────────────────────────────────────── --}}
    @if ($selectedSlot && $this->providerOptions->isNotEmpty())
        <div class="mt-6 border-t border-slate-100 pt-5">
            <h3 class="text-[15px] font-semibold text-slate-900">Qui intervient ?</h3>

            <div class="mt-3 space-y-2" role="radiogroup" aria-label="Professionnel">
                {{-- Le défaut, et il suffit : personne n'est obligé de comparer. --}}
                <button type="button" wire:click="selectProvider(null)"
                    role="radio" aria-checked="{{ $selectedProviderId === null ? 'true' : 'false' }}"
                    @class([
                        'flex w-full items-start gap-3 rounded-2xl border p-4 text-left transition',
                        'border-slate-900 bg-slate-50 ring-1 ring-slate-900' => $selectedProviderId === null,
                        'border-slate-200 bg-white hover:border-slate-300' => $selectedProviderId !== null,
                    ])>
                    <span class="min-w-0">
                        <span class="block text-[15px] font-medium text-slate-900">Attribution automatique</span>
                        <span class="mt-0.5 block text-sm text-slate-500">
                            Le meilleur professionnel disponible sur ce créneau. Recommandé.
                        </span>
                    </span>
                </button>

                @foreach ($this->providerOptions as $provider)
                    <button type="button" wire:click="selectProvider({{ $provider['id'] }})"
                        role="radio" aria-checked="{{ $selectedProviderId === $provider['id'] ? 'true' : 'false' }}"
                        @class([
                            'flex w-full items-start justify-between gap-3 rounded-2xl border p-4 text-left transition',
                            'border-slate-900 bg-slate-50 ring-1 ring-slate-900' => $selectedProviderId === $provider['id'],
                            'border-slate-200 bg-white hover:border-slate-300' => $selectedProviderId !== $provider['id'],
                        ])>
                        <span class="min-w-0">
                            <span class="block truncate text-[15px] font-medium text-slate-900">{{ $provider['name'] }}</span>
                            <span class="mt-0.5 block text-sm text-slate-500">
                                @if ($provider['rating'])
                                    {{ number_format($provider['rating'], 1, ',') }}/5
                                    <span class="text-slate-400">({{ $provider['rating_count'] }} avis)</span>
                                    ·
                                @endif
                                {{ $provider['missions_count'] }} mission{{ $provider['missions_count'] > 1 ? 's' : '' }}
                            </span>
                        </span>
                        <span class="shrink-0 text-sm tabular-nums text-slate-500">{{ $provider['distance_km'] }} km</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</section>
