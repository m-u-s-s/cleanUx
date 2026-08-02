{{--
    Le parcours de commande.

    L'ordre des écrans porte la première loi : le client voit son prix AVANT qu'on lui demande son
    nom. Rien ici ne réclame d'identité — ni compte, ni téléphone, ni carte.

    Mobile d'abord. Le récapitulatif est une barre BASSE, dans la zone du pouce, et l'action
    principale y vit. Rien de critique dans le tiers supérieur de l'écran.
--}}
<div class="pb-28 lg:pb-8">
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 lg:grid lg:max-w-7xl lg:grid-cols-[1fr_340px] lg:gap-8 lg:space-y-0">

        <div class="space-y-6">

            {{-- ─── Secteurs ────────────────────────────────────────────────────────────── --}}
            <section aria-labelledby="secteurs-titre">
                <h1 id="secteurs-titre" class="text-2xl font-semibold leading-tight text-slate-900">
                    De quoi avez-vous besoin ?
                </h1>
                <p class="mt-1 text-sm text-slate-500">Estimation immédiate, sans créer de compte.</p>

                <div class="mt-4">
                    @include('livewire.order-engine.partials.sector-carousel')
                </div>
            </section>

            {{-- ─── Métiers ─────────────────────────────────────────────────────────────── --}}
            @if ($sectorId && ! $tradeId)
                <section aria-labelledby="metiers-titre">
                    <h2 id="metiers-titre" class="text-lg font-semibold text-slate-900">Quel métier ?</h2>
                    <div class="mt-4">
                        @include('livewire.order-engine.partials.trade-dock')
                    </div>
                </section>
            @endif

            {{-- ─── Questionnaire ───────────────────────────────────────────────────────── --}}
            @if ($this->trade)
                <section aria-labelledby="questions-titre" class="rounded-2xl border border-slate-200 bg-white p-5">

                    <div class="flex items-start justify-between gap-4">
                        <div>
                            {{-- L'autre extrémité de la transition partagée : c'est ICI que
                                 l'élément du dock arrive. --}}
                            <h2 id="questions-titre" class="text-lg font-semibold text-slate-900"
                                style="view-transition-name: cx-trade-choisi">
                                {{ $this->trade->name }}
                            </h2>
                            @if ($this->trade->short_description)
                                <p class="mt-0.5 text-sm text-slate-500">{{ $this->trade->short_description }}</p>
                            @endif
                        </div>

                        {{-- Revenir en arrière ne perd rien : les réponses vivent dans le panier. --}}
                        <button type="button" wire:click="backToTrades"
                            class="shrink-0 text-sm font-medium text-slate-500 underline underline-offset-4 hover:text-slate-900">
                            Changer
                        </button>
                    </div>

                    {{--
                        Le mode change la nature des questions et la structure du prix : il est donc
                        choisi tôt et visiblement. Seuls ceux que le métier autorise apparaissent.
                    --}}
                    @if (count($this->availableModes) > 1)
                        <div class="mt-4 inline-flex rounded-xl bg-slate-100 p-1" role="radiogroup" aria-label="Type de prestation">
                            @foreach ($this->availableModes as $available)
                                <button type="button" wire:click="setMode('{{ $available }}')"
                                    role="radio" aria-checked="{{ $mode === $available ? 'true' : 'false' }}"
                                    @class([
                                        'min-h-[40px] rounded-lg px-4 text-sm font-medium transition',
                                        'bg-white text-slate-900 shadow-sm' => $mode === $available,
                                        'text-slate-600' => $mode !== $available,
                                    ])>
                                    {{ ['scheduled' => 'Planifié', 'asap' => 'Dès que possible', 'bundle' => 'Plusieurs services'][$available] ?? $available }}
                                </button>
                            @endforeach
                        </div>

                        @if ($mode === 'asap')
                            <p class="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                Service immédiat : quelques questions seulement, une majoration d’urgence,
                                et une estimation plus large. Tout est annoncé avant de confirmer.
                            </p>
                        @endif
                    @endif

                    {{--
                        L'intention d'entrée n'a pas pu être honorée — un ravalement de façade n'est
                        pas un service immédiat. On le DIT : basculer en silence laisserait le
                        client croire qu'il a commandé une intervention dans l'heure.
                    --}}
                    @if ($modeNotice)
                        <p class="mt-3 rounded-xl bg-slate-100 px-4 py-3 text-sm text-slate-700" role="status">
                            {{ $modeNotice }}
                        </p>
                    @endif

                    <div class="mt-2">
                        @foreach ($this->visibleQuestions as $question)
                            @livewire('order-engine.question-renderer',
                                ['question' => $question, 'value' => $answers[$question->code] ?? null],
                                key('q-'.$question->id.'-'.$mode))
                        @endforeach
                    </div>
                </section>

                {{-- La photo est un RACCOURCI, offert avant l'adresse : elle remplace des questions,
                     elle ne s'ajoute pas à la file. --}}
                @include('livewire.order-engine.partials.photos')

                {{-- L'adresse vient APRÈS les questions : elle récompense, elle ne filtre pas. --}}
                @include('livewire.order-engine.partials.address-availability')

                {{-- Et le calendrier après l'adresse : les créneaux dépendent de qui couvre la zone. --}}
                @if ($mode === 'scheduled')
                    @include('livewire.order-engine.partials.schedule')
                @endif
            @endif

            {{--
                Le chantier multi-métiers vit HORS du bloc « un métier choisi » : il doit rester
                visible quand le client revient au dock pour en ajouter un autre, sinon il perd de
                vue ce qu'il a déjà composé.
            --}}
            @if ($mode === 'bundle')
                @include('livewire.order-engine.partials.bundle')
            @endif
        </div>

        {{-- ─── Récapitulatif ───────────────────────────────────────────────────────────── --}}
        @if ($this->quote)
            {{--
                Carte collante sur grand écran, barre basse sur mobile — jamais une fenêtre modale.
                Le client doit savoir en permanence où il en est et ce qu'il va payer, sans avoir à
                ouvrir quoi que ce soit.
            --}}
            {{--
                Le prix change à chaque réponse, et ce changement est ANNONCÉ. Sans région vivante,
                l'utilisateur d'un lecteur d'écran répond aux questions sans jamais savoir que le
                montant a bougé — or c'est précisément l'information qui l'aide à décider.

                « polite » et non « assertive » : l'annonce attend une pause plutôt que de couper la
                lecture de la question en cours.
            --}}
            <aside class="hidden lg:sticky lg:top-6 lg:block lg:self-start" aria-label="Estimation"
                aria-live="polite" aria-atomic="true">
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Votre estimation</h2>

                    @if ($this->quote->quoteOnly)
                        <p class="mt-3 text-sm text-slate-600">
                            Ce métier demande un devis : un professionnel chiffre après avoir vu les lieux.
                        </p>
                    @else
                        {{--
                            Le montant SE DÉPLACE, il ne saute pas.

                            `tabular-nums` empêchait déjà les chiffres de se décaler latéralement,
                            mais la valeur elle-même passait d'un coup de 120 à 165 : le client voit
                            un nombre différent sans percevoir qu'il a changé, ni de combien. Le
                            compte progressif rend la variation lisible — et c'est cette variation,
                            pas le montant, qui l'aide à décider.
                        --}}
                        <p class="mt-2 text-3xl font-semibold tabular-nums text-slate-900">
                            @if ($this->quote->isExact())
                                <span data-cx-price="{{ $this->quote->minCents }}">{{ number_format($this->quote->minCents / 100, 0, ',', ' ') }}</span> €
                            @else
                                <span data-cx-price="{{ $this->quote->minCents }}">{{ number_format($this->quote->minCents / 100, 0, ',', ' ') }}</span>
                                – <span data-cx-price="{{ $this->quote->maxCents }}">{{ number_format($this->quote->maxCents / 100, 0, ',', ' ') }}</span> €
                            @endif
                        </p>

                        @if ($this->lastChange)
                            <p class="mt-1 text-sm text-slate-500">{{ $this->lastChange['label'] }}</p>
                        @endif

                        <ul class="mt-4 space-y-1.5 border-t border-slate-100 pt-4 text-sm">
                            @foreach ($this->quote->lines as $line)
                                <li class="flex items-baseline justify-between gap-3">
                                    <span class="min-w-0 truncate text-slate-600">{{ $line['label'] }}</span>
                                    <span class="shrink-0 tabular-nums text-slate-900">
                                        {{ number_format($line['min_cents'] / 100, 0, ',', ' ') }} €
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Vers le récapitulatif : toujours accessible, même incomplet — c'est là que
                         ce qui manque est écrit en toutes lettres, pas ici sous un bouton mort. --}}
                    <a href="{{ route('order.confirmation') }}" wire:navigate
                        class="mt-5 inline-flex min-h-[48px] w-full items-center justify-center rounded-xl bg-slate-900 text-sm font-medium text-white transition hover:bg-slate-800">
                        Continuer
                    </a>
                </div>
            </aside>

            {{-- Zone du pouce : sur mobile, l'action principale est EN BAS, jamais en haut. --}}
            {{-- Même annonce sur mobile : c'est la barre du pouce qui porte le prix. --}}
            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur lg:hidden"
                aria-live="polite" aria-atomic="true">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        {{--
                            Le micro-libellé de variation vivait sur le DESKTOP seulement.

                            Sur un produit conçu à 390 px d'abord, c'était le mauvais sens : le
                            client mobile voyait le montant bouger sans jamais savoir POURQUOI. Il
                            remplace ici le nom du métier — déjà affiché en titre juste au-dessus —
                            quand il y a quelque chose à expliquer.
                        --}}
                        <p class="truncate text-xs text-slate-500">
                            {{ $this->lastChange['label'] ?? $this->trade?->name }}
                        </p>
                        <p class="text-xl font-semibold tabular-nums leading-tight text-slate-900">
                            @if ($this->quote->quoteOnly)
                                Sur devis
                            @elseif ($this->quote->isExact())
                                <span data-cx-price="{{ $this->quote->minCents }}">{{ number_format($this->quote->minCents / 100, 0, ',', ' ') }}</span> €
                            @else
                                <span data-cx-price="{{ $this->quote->minCents }}">{{ number_format($this->quote->minCents / 100, 0, ',', ' ') }}</span>–<span data-cx-price="{{ $this->quote->maxCents }}">{{ number_format($this->quote->maxCents / 100, 0, ',', ' ') }}</span> €
                            @endif
                        </p>
                    </div>

                    <a href="{{ route('order.confirmation') }}" wire:navigate
                        class="inline-flex min-h-[48px] shrink-0 items-center rounded-xl bg-slate-900 px-6 text-sm font-medium text-white">
                        Continuer
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    /**
     * Le montant se deplace, il ne saute pas.
     *
     * Livewire remplace le texte du montant d'un coup : 120 devient 165 sans que rien ne signale
     * le changement. Le client lit un nombre different sans percevoir qu'il a bouge, ni de
     * combien — or c'est la VARIATION qui l'aide a decider, pas le montant.
     *
     * On observe donc l'attribut `data-cx-price` (des centimes, jamais du texte formate : reparser
     * « 1 250 » dependrait de la locale) et on compte de l'ancienne valeur vers la nouvelle.
     *
     * En mouvement reduit, aucune animation : la valeur finale est ecrite immediatement.
     */
    (() => {
        const REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)');
        const DUREE = 420;

        const format = (cents) => new Intl.NumberFormat('fr-BE', {
            maximumFractionDigits: 0,
        }).format(Math.round(cents / 100));

        const anime = (el, depuis, vers) => {
            if (REDUCED.matches || depuis === vers) {
                el.textContent = format(vers);
                return;
            }

            const debut = performance.now();

            const pas = (maintenant) => {
                const t = Math.min((maintenant - debut) / DUREE, 1);
                // Sortie douce : le chiffre ralentit en arrivant, comme un compteur mecanique.
                const eased = 1 - Math.pow(1 - t, 3);
                el.textContent = format(depuis + (vers - depuis) * eased);

                if (t < 1) {
                    requestAnimationFrame(pas);
                }
            };

            requestAnimationFrame(pas);
        };

        const observe = (el) => {
            if (el.dataset.cxPriceWatched) {
                return;
            }
            el.dataset.cxPriceWatched = '1';
            el.dataset.cxPriceShown = el.dataset.cxPrice;

            new MutationObserver(() => {
                const vers = Number(el.dataset.cxPrice);
                const depuis = Number(el.dataset.cxPriceShown ?? vers);

                if (Number.isFinite(vers) && Number.isFinite(depuis)) {
                    el.dataset.cxPriceShown = String(vers);
                    anime(el, depuis, vers);
                }
            }).observe(el, { attributes: true, attributeFilter: ['data-cx-price'] });
        };

        const scan = () => document.querySelectorAll('[data-cx-price]').forEach(observe);

        document.addEventListener('DOMContentLoaded', scan);
        document.addEventListener('livewire:navigated', scan);
        // Livewire remplace des noeuds a chaque reponse : les nouveaux montants doivent etre
        // observes a leur tour, sinon l'animation ne marche qu'au premier rendu.
        document.addEventListener('livewire:update', scan);
        scan();
    })();
</script>
@endpush
