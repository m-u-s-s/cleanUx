{{--
    L'écran d'attente d'une course immédiate.

    Le plus anxiogène du parcours : le client attend sans rien contrôler. Le rayon s'élargit à vue,
    le compteur est réel, et le bouton d'annulation reste visible avec son coût annoncé AVANT le
    clic. Cacher ce bouton ne retient personne : le client ferme l'onglet.
--}}
<div class="pb-32 lg:pb-8" wire:poll.5s="tick">
    @php($request = $this->request)

    <div class="mx-auto max-w-2xl space-y-5 px-4 py-6">

        @if (! $request)
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center">
                <h1 class="text-xl font-semibold text-slate-900">Demande introuvable</h1>
                <a href="{{ route('order.journey') }}" wire:navigate
                    class="mt-6 inline-flex min-h-[48px] items-center rounded-xl bg-slate-900 px-6 text-sm font-medium text-white">
                    Composer une commande
                </a>
            </div>
        @else
            {{-- ─── Ce qui se passe, en une phrase ──────────────────────────────────────── --}}
            <div class="text-center">
                <h1 class="text-2xl font-semibold leading-tight text-slate-900">
                    @switch($request->status)
                        @case(\App\Support\Domain\AsapStatus::SEARCHING)
                            Recherche d’un professionnel
                            @break
                        @case(\App\Support\Domain\AsapStatus::ACCEPTED)
                            {{ $request->acceptedBy?->name ?? 'Un professionnel' }} a accepté
                            @break
                        @case(\App\Support\Domain\AsapStatus::EN_ROUTE)
                            En route vers vous
                            @break
                        @case(\App\Support\Domain\AsapStatus::ARRIVED)
                            Arrivé sur place
                            @break
                        @case(\App\Support\Domain\AsapStatus::IN_PROGRESS)
                            Intervention en cours
                            @break
                        @case(\App\Support\Domain\AsapStatus::EXPIRED)
                            Personne n’a répondu pour l’instant
                            @break
                        @case(\App\Support\Domain\AsapStatus::CANCELLED)
                            Demande annulée
                            @break
                        @default
                            {{ \App\Support\Domain\AsapStatus::label($request->status) }}
                    @endswitch
                </h1>
                <p class="mt-1 text-sm text-slate-500">{{ $request->trade?->name }}</p>
            </div>

            {{-- ─── La carte : votre adresse, et jusqu'où on cherche ─────────────────────── --}}
            @if ($request->lat !== null && $request->lng !== null)
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
                    x-data="asapSearchMap({
                        lat: {{ (float) $request->lat }},
                        lng: {{ (float) $request->lng }},
                        radius: {{ (int) $request->radius_m }},
                    })"
                    x-init="boot()"
                    {{-- La clé porte le rayon : élargir remplace l'élément, donc redessine la
                         carte. Les battements qui ne changent rien la laissent intacte, et
                         l'écran ne clignote pas toutes les cinq secondes. --}}
                    wire:key="carte-{{ $request->id }}-{{ $request->radius_m }}">
                    <div id="asap-map" style="height: 38vh; min-height: 220px; background: var(--brio-border);"
                        role="img"
                        aria-label="Zone de recherche autour de votre adresse, rayon {{ (int) round($request->radius_m / 1000) }} kilomètres"></div>
                </div>
            @endif

            {{-- ─── L'attente, habitée par des chiffres VRAIS ────────────────────────────── --}}
            @if ($request->status === \App\Support\Domain\AsapStatus::SEARCHING)
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Rayon</p>
                            <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900">
                                {{ number_format($request->radius_m / 1000, 0, ',', ' ') }} km
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Prévenus</p>
                            {{-- Un compteur réel : ceux dont on connaît la position et qui
                                 exercent le métier. Gonfler ce chiffre détruit la confiance. --}}
                            <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900">
                                {{ $request->notified_count }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Depuis</p>
                            <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900">
                                {{ (int) floor($request->elapsedSeconds() / 60) }} min
                            </p>
                        </div>
                    </div>

                    @if ($request->notified_count === 0)
                        {{-- Dire la vérité tôt vaut mieux qu'un sablier qui tourne pour rien. --}}
                        <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            Personne n’est encore joignable dans ce rayon. Vous pouvez chercher plus loin.
                        </p>
                    @endif

                    <button type="button" wire:click="expand"
                        class="mt-4 min-h-[48px] w-full rounded-xl border border-slate-300 bg-white text-sm font-medium text-slate-900">
                        Chercher plus loin
                    </button>
                </div>
            @endif

            {{-- ─── Personne n'a répondu : jamais un simple constat ──────────────────────── --}}
            @if ($request->status === \App\Support\Domain\AsapStatus::EXPIRED)
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-sm text-slate-600">
                        Aucun professionnel ne s’est libéré. Voici ce que vous pouvez faire :
                    </p>

                    {{--
                        LES TROIS SORTIES, ET AUCUNE N'EST UN CONSTAT.

                        Le client a déjà décidé, déjà donné son adresse. Lui rendre « aucun
                        professionnel disponible » sans action est un bug produit. Chacune des trois
                        est donc un GESTE, pas une explication.
                    --}}
                    <ul class="mt-4 space-y-3">
                        <li class="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                            <p class="text-sm font-medium text-slate-900">Continuer à attendre</p>
                            <p class="mt-0.5 text-sm text-slate-600">
                                Nous élargissons la recherche et rappelons les professionnels qui
                                viennent de se libérer.
                            </p>
                            <button type="button" wire:click="retry"
                                class="mt-3 min-h-[44px] w-full rounded-xl bg-slate-900 text-sm font-medium text-white">
                                Chercher encore
                            </button>
                        </li>

                        <li class="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                            <p class="text-sm font-medium text-slate-900">Convertir en rendez-vous</p>
                            <p class="mt-0.5 text-sm text-slate-600">
                                Le même service, à une heure que vous choisissez —
                                <strong>sans re-saisir votre commande ni payer à nouveau</strong>.
                            </p>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <input type="datetime-local" wire:model="scheduledAt"
                                    class="min-h-[44px] flex-1 rounded-xl border-slate-300 text-sm">
                                <button type="button" wire:click="convertToScheduled"
                                    class="min-h-[44px] rounded-xl border border-slate-300 bg-white px-4 text-sm font-medium text-slate-900">
                                    Prendre rendez-vous
                                </button>
                            </div>
                        </li>

                        <li class="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                            <p class="text-sm font-medium text-slate-900">Annuler la demande</p>
                            <p class="mt-0.5 text-sm text-slate-600">
                                Aucun montant n’a été prélevé : le paiement n’est engagé qu’à partir
                                du moment où un professionnel accepte.
                            </p>
                            <button type="button" wire:click="abandon"
                                wire:confirm="Annuler définitivement cette demande ?"
                                class="mt-3 min-h-[44px] w-full rounded-xl border border-rose-300 text-sm font-medium text-rose-700">
                                Annuler sans frais
                            </button>
                        </li>
                    </ul>
                </div>
            @endif

            {{-- ─── Accepté : la course devient une intervention réelle ──────────────────── --}}
            @if (in_array($request->status, [
                \App\Support\Domain\AsapStatus::ACCEPTED,
                \App\Support\Domain\AsapStatus::EN_ROUTE,
                \App\Support\Domain\AsapStatus::ARRIVED,
                \App\Support\Domain\AsapStatus::IN_PROGRESS,
            ], true))
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-sm text-slate-600">
                        {{ $request->acceptedBy?->name ?? 'Votre professionnel' }} prend en charge votre
                        intervention. Vous pouvez suivre son arrivée en direct.
                    </p>

                    @php($bookingId = $request->item?->metadata['booking_id'] ?? null)
                    @if ($bookingId)
                        <a href="{{ route('client.dashboard') }}?booking={{ $bookingId }}"
                            class="mt-4 inline-flex min-h-[48px] w-full items-center justify-center rounded-xl bg-slate-900 text-sm font-medium text-white">
                            Suivre l’intervention
                        </a>
                    @endif
                </div>
            @endif

            @if ($error)
                <p class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">{{ $error }}</p>
            @endif

            {{-- ─── L'annulation : TOUJOURS visible, coût annoncé AVANT ──────────────────── --}}
            @if ($request->isOpen())
                <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur lg:static lg:border-0 lg:bg-transparent lg:px-0 lg:backdrop-blur-none">
                    <div class="mx-auto max-w-2xl">
                        {{-- Ce que ça coûte, LU avant le clic. C'est la différence entre un client
                             qui décide et un client qui découvre. --}}
                        <p class="mb-2 text-center text-sm {{ $this->cancellation['free'] ? 'text-slate-500' : 'text-amber-900' }}">
                            {{ $this->cancellation['reason'] }}
                        </p>

                        <button type="button" wire:click="cancel"
                            wire:confirm="{{ $this->cancellation['free']
                                ? 'Annuler cette demande ?'
                                : 'Annuler maintenant coûte '.number_format($this->cancellation['fee_cents'] / 100, 2, ',', ' ').' €. Confirmer ?' }}"
                            class="min-h-[48px] w-full rounded-xl border border-slate-300 bg-white text-sm font-medium text-slate-900">
                            Annuler la demande
                        </button>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    window.asapSearchMap = (cfg) => ({
        map: null,

        boot() {
            if (typeof L === 'undefined') {
                return;
            }

            // L'élément est remplacé à chaque changement de rayon : Leaflet refuse d'initialiser
            // deux fois le même conteneur, on nettoie donc avant de redessiner.
            const container = this.$el.querySelector('#asap-map');

            if (container && container._leaflet_id) {
                container._leaflet_id = null;
            }

            this.map = L.map('asap-map', {
                zoomControl: false,
                attributionControl: false,
                // La carte informe, elle ne se manipule pas : le client attend, il n'explore pas.
                dragging: false,
                scrollWheelZoom: false,
            }).setView([cfg.lat, cfg.lng], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(this.map);
            L.marker([cfg.lat, cfg.lng]).addTo(this.map);

            const circle = L.circle([cfg.lat, cfg.lng], {
                radius: cfg.radius,
                color: window.brioJeton('--brio-ink', '#0f172a'),
                weight: 1,
                fillColor: window.brioJeton('--brio-ink', '#0f172a'),
                fillOpacity: 0.06,
            }).addTo(this.map);

            this.map.fitBounds(circle.getBounds(), { padding: [16, 16] });
        },
    });
</script>
@endpush
