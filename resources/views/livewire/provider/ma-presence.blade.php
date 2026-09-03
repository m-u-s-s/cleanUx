{{--
    MA PRÉSENCE — l'écran répond à une seule question, et la pose en grand : est-ce que je reçois
    des missions, là, maintenant ?

    Le reste de la page n'existe que pour agir sur cette réponse. Un tableau de bord de plus, avec
    six indicateurs et aucun geste, aurait laissé le prestataire aussi seul que le JSON d'avant.

    LA POSITION VIENT DU NAVIGATEUR. Sans elle, le répartiteur cherche par distance et ne trouve
    personne : le bouton la demande avant d'appeler le serveur, et le dit s'il ne l'obtient pas.
--}}
<div class="space-y-6"
     x-data="{
        cadenceMs: {{ (int) config('presence.heartbeat_interval_seconds', 60) * 1000 }},
        battement: null,

        position(action) {
            if (! navigator.geolocation) {
                $wire.set('erreur', @js(__('Votre navigateur ne partage pas votre position : le répartiteur ne pourra pas vous situer.')));
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (p) => $wire.call(action, p.coords.latitude, p.coords.longitude),
                () => $wire.set('erreur', @js(__('Position refusée. Autorisez la géolocalisation, sinon aucune mission ne vous sera proposée.'))),
                { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 },
            );
        },

        /* LE BATTEMENT NE S'ARRÊTE PAS À LA PREMIÈRE ERREUR : une coupure de réseau de dix
           secondes ne doit pas rendre le prestataire injoignable jusqu'à ce qu'il recharge. */
        demarrerLeBattement() {
            clearInterval(this.battement);
            this.battement = setInterval(() => this.position('signaler'), this.cadenceMs);
        },
     }"
     x-init="@if($this->verdict['joignable']) demarrerLeBattement() @endif"
     x-on:presence-en-ligne.window="demarrerLeBattement()"
     x-on:presence-hors-ligne.window="clearInterval(battement)">

    <x-page-shell
        :eyebrow="__('Missions')"
        :title="__('Ma présence')"
        :subtitle="__('Le répartiteur ne propose une mission qu’à un prestataire en ligne, localisé et récemment vu.')" />

    @if($message)
        <div class="brio-alerte brio-alerte-success !mb-0"><span aria-hidden="true">✅</span><span>{{ $message }}</span></div>
    @endif

    @if($erreur)
        <div class="brio-alerte brio-alerte-danger !mb-0"><span aria-hidden="true">⚠️</span><span>{{ $erreur }}</span></div>
    @endif

    {{-- LA RÉPONSE, EN GRAND. C'est la seule chose que le prestataire vient chercher. --}}
    {{-- LA FORME EN BLOC, PAS LA FORME EN LIGNE : melanger les deux dans un meme fichier
         casse la compilation Blade — le `@endphp` du bloc plus bas fermerait celle-ci. --}}
    @php
        $verdict = $this->verdict;
    @endphp
    <x-app-card>
        <div class="flex flex-col items-center gap-3 py-4 text-center">
            <span class="relative flex h-4 w-4" aria-hidden="true">
                @if($verdict['joignable'])
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75 motion-reduce:animate-none"></span>
                    <span class="relative inline-flex h-4 w-4 rounded-full bg-emerald-500"></span>
                @else
                    <span class="relative inline-flex h-4 w-4 rounded-full bg-slate-400"></span>
                @endif
            </span>

            <p class="text-xl font-black tracking-tight md:text-2xl">
                {{ $verdict['joignable'] ? __('Vous recevez des missions') : __('Vous ne recevez pas de mission') }}
            </p>

            @if($verdict['motif'])
                <p class="max-w-xl text-sm opacity-70">{{ $verdict['motif'] }}</p>
            @else
                <p class="max-w-xl text-sm opacity-70">
                    {{ __('Votre position est partagée toutes les :secondes secondes tant que cet écran reste ouvert.', [
                        'secondes' => (int) config('presence.heartbeat_interval_seconds', 60),
                    ]) }}
                </p>
            @endif

            <div class="mt-2 flex flex-wrap items-center justify-center gap-2">
                @if($this->presence->status === \App\Models\ProviderPresence::STATUS_ONLINE)
                    <button type="button" x-on:click="position('signaler')" class="brio-btn-ligne">
                        {{ __('Actualiser ma position') }}
                    </button>
                    <button type="button" wire:click="mettreEnPause"
                            class="brio-btn-ligne">{{ __('Mettre en pause') }}</button>
                    <button type="button" wire:click="passerHorsLigne"
                            class="brio-btn-ligne-danger">{{ __('Passer hors ligne') }}</button>
                @else
                    <button type="button" x-on:click="position('passerEnLigne')" class="brio-btn-primary">
                        {{ __('Passer en ligne') }}
                    </button>
                    @if($this->presence->status !== \App\Models\ProviderPresence::STATUS_OFFLINE)
                        <button type="button" wire:click="passerHorsLigne"
                                class="brio-btn-ligne-danger">{{ __('Passer hors ligne') }}</button>
                    @endif
                @endif
            </div>
        </div>
    </x-app-card>

    <div class="grid gap-4 lg:grid-cols-3">

        {{-- LES TROIS CONDITIONS, UNE PAR UNE : savoir laquelle manque vaut mieux qu'un refus global. --}}
        <x-app-card class="lg:col-span-2" :title="__('Ce que le répartiteur regarde')"
                    :subtitle="__('Les trois conditions sont vérifiées ensemble. Une seule qui manque vous rend invisible.')">
            @php
                $presence = $this->presence;
                $positionConnue = $presence->current_lat !== null && $presence->current_lng !== null;
                $signalFrais = $presence->heartbeat_at !== null
                    && $presence->heartbeat_at->gte(now()->subMinutes($this->fraicheurMinutes));

                $conditions = [
                    [
                        __('Statut en ligne'),
                        $presence->status === \App\Models\ProviderPresence::STATUS_ONLINE,
                        match ($presence->status) {
                            \App\Models\ProviderPresence::STATUS_ONLINE => __('En ligne'),
                            \App\Models\ProviderPresence::STATUS_BUSY => __('En mission'),
                            \App\Models\ProviderPresence::STATUS_ON_BREAK => __('En pause'),
                            default => __('Hors ligne'),
                        },
                    ],
                    [
                        __('Position connue'),
                        $positionConnue,
                        $positionConnue
                            ? number_format((float) $presence->current_lat, 4).', '.number_format((float) $presence->current_lng, 4)
                            : __('Aucune coordonnée enregistrée'),
                    ],
                    [
                        __('Signal récent'),
                        $signalFrais,
                        $presence->heartbeat_at
                            ? $presence->heartbeat_at->diffForHumans()
                            : __('Jamais signalé'),
                    ],
                ];
            @endphp

            <div class="space-y-2">
                @foreach($conditions as [$libelle, $remplie, $detail])
                    <div class="brio-list-item !p-3" wire:key="cond-{{ $loop->index }}">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">{{ $libelle }}</p>
                            <p class="text-xs opacity-70">{{ $detail }}</p>
                        </div>
                        <x-ui.badge :tone="$remplie ? 'success' : 'warning'"
                                    :label="$remplie ? __('Remplie') : __('Manquante')" />
                    </div>
                @endforeach
            </div>
        </x-app-card>

        <div class="space-y-4">
            <x-app-card :title="__('Mon rayon')"
                        :subtitle="__('Au-delà, aucune mission ne vous est proposée.')">
                <form wire:submit="enregistrerLeRayon" class="space-y-2">
                    <label for="rayon" class="block text-sm font-semibold">{{ __('Rayon (km)') }}</label>
                    <input id="rayon" wire:model="rayon" type="number" min="1" max="200" class="w-full">
                    @error('rayon') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    <button type="submit" class="brio-btn-ligne w-full">{{ __('Enregistrer') }}</button>
                </form>
            </x-app-card>

            <x-app-card :title="__('Mon temps en ligne')">
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="opacity-70">{{ __('Aujourd’hui') }}</span>
                        <span class="font-semibold tabular-nums">{{ (int) $this->presence->online_minutes_today }} {{ __('min') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="opacity-70">{{ __('Cette semaine') }}</span>
                        <span class="font-semibold tabular-nums">{{ (int) $this->presence->online_minutes_week }} {{ __('min') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="opacity-70">{{ __('Dernière mise en ligne') }}</span>
                        <span class="font-semibold">
                            {{ $this->presence->last_online_at?->diffForHumans() ?? __('Jamais') }}
                        </span>
                    </div>
                </div>
            </x-app-card>
        </div>
    </div>
</div>
