{{--
    LA MODALE D'OFFRE, CÔTÉ WEB.

    Le sondage court est le REPLI, pas le luxe : la diffusion temps réel est éteinte par défaut sur
    ce dépôt, et une modale qui n'apparaîtrait qu'avec une socket vivante serait invisible partout
    où l'exploitation n'a pas configuré Reverb.

    LE COMPTE À REBOURS SUIT L'HORLOGE SERVEUR. `expires_at` est rendu en ISO-8601 et Alpine compte
    dessus : un décompte parti de vingt à l'affichage afficherait encore six secondes sur une offre
    déjà escaladée.
--}}
<div wire:poll.3s="tick" data-chrome="provider-offer-watcher">
    @if ($this->payload)
        @php($offre = $this->payload)

        <div
            x-data="{
                expiresAt: new Date(@js($offre['expires_at'])).getTime(),
                total: {{ max(1, (int) ($offre['ttl_seconds'] ?? 20)) }},
                restant: 0,
                minuteur: null,
                init() {
                    this.calculer();
                    this.minuteur = setInterval(() => this.calculer(), 250);
                },
                destroy() { if (this.minuteur) clearInterval(this.minuteur); },
                calculer() {
                    this.restant = Math.max(0, Math.ceil((this.expiresAt - Date.now()) / 1000));
                    if (this.restant === 0 && this.minuteur) {
                        clearInterval(this.minuteur);
                        this.minuteur = null;
                        {{-- Le serveur a déjà escaladé : on ferme, on ne laisse pas cliquer. --}}
                        $wire.tick();
                    }
                },
            }"
            class="fixed inset-0 z-[60] flex items-end justify-center bg-slate-950/70 p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="offre-titre"
        >
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-white/10 dark:bg-slate-900">
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-white/10">
                    <div
                        class="h-full rounded-full bg-amber-500 transition-[width] duration-200 ease-linear"
                        :style="`width: ${Math.min(100, (restant / total) * 100)}%`"
                    ></div>
                </div>
                <p class="mt-1 text-right text-xs text-slate-500 dark:text-slate-400">
                    <span x-text="restant">—</span> s pour répondre
                </p>

                <p class="mt-4 text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400">
                    Nouvelle mission
                </p>
                <h2 id="offre-titre" class="text-2xl font-bold text-slate-900 dark:text-white">
                    {{ $offre['trade_name'] ?? $offre['service_name'] ?? 'Intervention' }}
                </h2>

                <dl class="mt-4 space-y-2 rounded-xl bg-slate-50 p-4 text-sm dark:bg-white/5">
                    <div class="flex items-center justify-between gap-4">
                        {{-- Sur une course, préciser DE QUELLE distance on parle : celle qui reste
                             à faire pour aller chercher le client, pas celle de la course. --}}
                        <dt class="text-slate-500 dark:text-slate-400">
                            {{ ($offre['is_ride'] ?? false) ? 'Pour aller le chercher' : 'Distance' }}
                        </dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">
                            {{ $offre['distance_km'] !== null ? $offre['distance_km'].' km' : '—' }}
                        </dd>
                    </div>

                    {{-- La longueur de la course : la question qui décide d'accepter. --}}
                    @if (($offre['is_ride'] ?? false) && ($offre['ride_distance_km'] ?? null) !== null)
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Course</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">
                            {{ str_replace('.', ',', (string) $offre['ride_distance_km']) }} km
                            @if (($offre['ride_duration_minutes'] ?? null) !== null)
                                · {{ $offre['ride_duration_minutes'] }} min
                            @endif
                        </dd>
                    </div>
                    @endif
                    {{--
                        QUAND. La modale disait où, combien et à quelle distance — jamais à quelle
                        date. Sur une mission planifiée dans dix jours, c'est pourtant la première
                        question : le prestataire acceptait un engagement sans savoir pour quel
                        jour. La charge utile portait `scheduled_at` depuis toujours.
                    --}}
                    @if (($offre['scheduled_at'] ?? null) !== null)
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Quand</dt>
                        <dd class="text-right font-semibold text-slate-900 dark:text-white">
                            {{ \Illuminate\Support\Carbon::parse($offre['scheduled_at'])->translatedFormat('D j M à H\hi') }}
                        </dd>
                    </div>
                    @endif

                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Secteur</dt>
                        <dd class="text-right font-semibold text-slate-900 dark:text-white">
                            {{ $offre['approximate_address'] ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Rémunération</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">
                            {{ $offre['payout_cents'] !== null
                                ? number_format($offre['payout_cents'] / 100, 2, ',', ' ').' €'
                                : 'À confirmer' }}
                        </dd>
                    </div>
                </dl>

                @if ($error)
                    <p class="mt-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                        {{ $error }}
                    </p>
                @endif

                <div class="mt-5 flex gap-3">
                    <button
                        type="button"
                        wire:click="accept({{ (int) $offre['assignment_id'] }})"
                        wire:loading.attr="disabled"
                        class="flex-1 rounded-xl bg-indigo-600 px-4 py-3 font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-60"
                    >
                        Accepter
                    </button>
                    <button
                        type="button"
                        wire:click="decline({{ (int) $offre['assignment_id'] }})"
                        wire:loading.attr="disabled"
                        class="flex-1 rounded-xl border border-slate-300 px-4 py-3 font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-white/15 dark:text-slate-200 dark:hover:bg-white/5"
                    >
                        Refuser
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
