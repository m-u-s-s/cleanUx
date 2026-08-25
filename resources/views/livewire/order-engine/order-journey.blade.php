{{--
    Le parcours de commande.

    L'ordre des écrans porte la première loi : le client voit son prix AVANT qu'on lui demande son
    nom. Rien ici ne réclame d'identité — ni compte, ni téléphone, ni carte.

    Mobile d'abord. Le récapitulatif est une barre BASSE, dans la zone du pouce, et l'action
    principale y vit. Rien de critique dans le tiers supérieur de l'écran.
--}}
<div class="pb-28 lg:pb-8">
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 lg:grid lg:max-w-7xl lg:grid-cols-[1fr_340px] lg:gap-8 lg:space-y-0">

        {{--
            `min-w-0` N'EST PAS DÉCORATIF — sans lui, la colonne d'estimation sort de l'écran.

            Une colonne `1fr` vaut `minmax(auto, 1fr)`, et `auto` ne descend jamais sous la
            largeur MINIMALE de son contenu. Les carrousels de secteurs et de services qui
            vivent ici portent des cartes `shrink-0` : leur minimum dépasse la place
            disponible, la colonne s'élargit, et pousse le panneau « Votre estimation »
            hors du cadre.

            Mesuré sur le parcours réel, écran de 1440 px : le document défilait de 202 px
            vers la droite, et le prix — ce que le client vient chercher — était hors champ.
            À 390 px rien ne paraissait, les carrousels y défilant normalement : c'est le
            GRAND écran qui souffrait, pas le petit.
        --}}
        <div class="min-w-0 space-y-6">

            {{--
                ─── Intention ─────────────────────────────────────────────────────────────

                LA QUESTION NE SE POSE PAS DEUX FOIS.

                Dans l'application mobile, l'écran natif demande déjà « Quel type de mission ? » et
                ouvre cette page avec le mode retenu (`/commander?mode=asap`). Elle reposait
                pourtant les mêmes quatre cartes, juste en dessous du titre de l'écran qui venait
                de trancher — et la seconde réponse pouvait contredire la première sans que rien ne
                le signale.

                On ne masque que la RÉPÉTITION : quand le mode est déjà décidé et qu'on est
                embarqué. Sur le web, où personne n'a rien choisi en amont, les cartes restent le
                point d'entrée du parcours. Et le sélecteur compact de la fiche métier — juste
                sous le nom du service — permet toujours d'en changer d'un geste.
            --}}
            @php($modeDejaChoisi = ($embedded ?? false) && filled(request()->query('mode')))

            <section aria-labelledby="intention-titre">
                <h1 id="intention-titre" class="text-2xl font-semibold leading-tight text-slate-900">
                    De quoi avez-vous besoin ?
                </h1>
                <p class="mt-1 text-sm text-slate-500">Estimation immédiate, sans créer de compte.</p>

                @unless ($modeDejaChoisi)
                    <div class="mt-4">
                        @include('livewire.order-engine.partials.mode-cards')
                    </div>
                @endunless
            </section>

            {{--
                L'ASSISTANT (E5), AVANT le catalogue — et pas à sa place.

                Il s'adresse à celui qui ne sait pas nommer le métier dont il a besoin. Ceux qui le
                savent descendent d'une ligne et choisissent leur secteur : remplaçer le catalogue
                par un champ libre punirait les clients qui savent déjà ce qu'ils veulent.
            --}}
            @if (feature('ai_order_assistant'))
                @include('livewire.order-engine.partials.order-assistant')
            @endif

            {{-- ─── Secteurs ────────────────────────────────────────────────────────────── --}}
            {{--
                ETAPE FRANCHIE = ETAPE REPLIEE.

                Le carrousel restait deplie apres le choix : la page s'allongeait d'un ecran a
                chaque etape, et le client perdait de vue ce qu'il avait deja repondu. Replie, le
                secteur tient en une ligne qu'on rouvre d'un clic — et le nom choisi reste visible,
                ce qu'un simple masquage aurait perdu.
            --}}
            @if ($sectorId && $this->sector)
                <button type="button" wire:click="changerDeSecteur" class="brio-etape-faite" data-test="etape-secteur-repliee">
                    <span class="brio-etape-puce" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                    </span>
                    <span class="brio-etape-corps">
                        <span class="brio-etape-libelle">Domaine</span>
                        <span class="brio-etape-valeur">{{ $this->sector->translate('name') ?: $this->sector->name }}</span>
                    </span>
                    <span class="brio-etape-changer">Changer</span>
                </button>
            @endif

            <section aria-labelledby="secteurs-titre" @class(['brio-etape-repliee' => (bool) $sectorId])>
                <h2 id="secteurs-titre" class="text-lg font-semibold text-slate-900">Quel domaine ?</h2>

                <div class="mt-4">
                    @if ($this->sectors->isEmpty())
                        {{--
                            AUCUN SECTEUR NE SERT CETTE INTENTION. Le cas se produit vraiment : une
                            zone où l'exploitation n'a ouvert l'immédiat sur aucun métier. Rendre un
                            carrousel vide laisserait croire à une panne.
                        --}}
                        <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-sm text-slate-500"
                            data-test="no-sector-for-intent">
                            Aucun service n’est disponible dans ce mode
                            @if ($serviceZoneId) à cette adresse @endif.
                            <button type="button" wire:click="chooseIntent(null)"
                                class="font-semibold text-blue-600 hover:underline">
                                Voir tous les services
                            </button>
                        </p>
                    @else
                        @include('livewire.order-engine.partials.sector-carousel')
                    @endif
                </div>

                {{--
                    LA CASE « LOCATION » — un composant AUTONOME, et c'est la seule ligne que ce
                    module ajoute au parcours de commande.

                    Elle n'est pas un secteur : un secteur mène à des MÉTIERS puis à un dispatch de
                    prestataires, alors qu'une location n'a ni l'un ni l'autre. Créer une ligne
                    `Sector` « Location » aurait envoyé ce parcours chercher des métiers qui
                    n'existent pas. `OrderJourney.php` n'est donc pas modifié d'un caractère.

                    ELLE DISPARAÎT D'ELLE-MÊME quand aucune voiture n'est disponible : le composant
                    ne rend rien. Une porte qui promet du choix devant une vitrine vide apprend au
                    client que la plateforme annonce ce qu'elle ne sait pas faire.
                --}}
                @if (! $sectorId && ! $tradeId)
                    <div class="mt-6">
                        @livewire(\App\Livewire\Rental\LocationEntryTile::class)
                    </div>
                @endif
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
                                {{ $this->trade->translate('name') }}
                            </h2>
                            @if ($this->trade->translate('short_description'))
                                <p class="mt-0.5 text-sm text-slate-500">{{ $this->trade->translate('short_description') }}</p>
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

                    {{--
                        COMBIEN DE TEMPS ? — avant les questions, parce que sur une prestation
                        horaire la durée EST le prix. La placer après ferait découvrir le montant au
                        bout du parcours, alors que c'est la première chose que le client arbitre.

                        Ce bloc ne s'affiche que sur les métiers facturés à l'heure ; il se rend nul
                        de lui-même partout ailleurs.
                    --}}
                    @include('livewire.order-engine.partials.hours')

                    {{--
                        L'indicateur de progression, et il est HONNÊTE.

                        Il compte les étapes réellement visibles à cet instant, pas celles qui
                        existent en base : une étape dont toutes les questions sont masquées par une
                        condition n'existe plus pour ce client. Annoncer « étape 2 sur 3 » puis
                        sauter la troisième serait un compte que le client prendrait en défaut.

                        Rien ne s'affiche quand il n'y a qu'une étape : un questionnaire court n'a
                        pas besoin de cérémonie.
                    --}}
                    @if ($this->stepCount() > 1)
                        <div class="mt-4" aria-live="polite">
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="text-sm font-medium text-slate-900">
                                    {{ $this->currentStepTitle() ?? 'Étape '.($stepIndex + 1) }}
                                </p>
                                <p class="text-xs tabular-nums text-slate-500">
                                    Étape {{ $stepIndex + 1 }} sur {{ $this->stepCount() }}
                                </p>
                            </div>

                            <div class="mt-2 h-1 overflow-hidden rounded-full bg-slate-100"
                                role="progressbar"
                                aria-valuemin="1"
                                aria-valuemax="{{ $this->stepCount() }}"
                                aria-valuenow="{{ $stepIndex + 1 }}"
                                aria-label="Progression du questionnaire">
                                <div class="h-full rounded-full bg-slate-900 transition-[width] duration-300"
                                    style="width: {{ round((($stepIndex + 1) / $this->stepCount()) * 100) }}%"></div>
                            </div>
                        </div>
                    @endif

                    <div class="mt-2">
                        @foreach ($this->visibleQuestions as $question)
                            {{--
                                Une question conditionnelle apparaissait D'UN COUP, décalant tout
                                ce qui la suit. Sur un écran où le prix bouge au même instant, ce
                                saut fait perdre le fil de ce qui vient de se passer.

                                `x-collapse` anime la hauteur, et respecte `prefers-reduced-motion`
                                de lui-même : l'information reste, le mouvement s'en va.
                            --}}
                            <div x-data x-show="true" x-collapse.duration.250ms
                                wire:key="wrap-{{ $question->id }}-{{ $mode }}">
                                @livewire('order-engine.question-renderer',
                                    ['question' => $question, 'value' => $answers[$question->code] ?? null],
                                    key('q-'.$question->id.'-'.$mode))
                            </div>
                        @endforeach
                    </div>

                    {{--
                        La connexion perdue est DITE, et ce qui est répondu est déjà sauvé.

                        Chaque réponse part au serveur au fil de l'eau : une coupure ne perd rien de
                        ce qui est passé. Encore faut-il le dire — sinon le client recommence, ou
                        pire, abandonne en croyant avoir tout perdu.
                    --}}
                    <div x-data="{ enligne: true }"
                        x-init="
                            enligne = navigator.onLine;
                            window.addEventListener('online', () => enligne = true);
                            window.addEventListener('offline', () => enligne = false);
                        ">
                        <p x-show="! enligne" x-cloak role="status"
                            class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            Connexion perdue. Vos réponses sont enregistrées : reprenez dès que le
                            réseau revient, rien ne sera à ressaisir.
                        </p>
                    </div>

                    {{-- La navigation entre étapes. Revenir ne perd rien : les réponses vivent dans
                         le panier, pas à l'écran. --}}
                    @if ($this->stepCount() > 1)
                        <div class="mt-4 flex items-center gap-3">
                            @if ($stepIndex > 0)
                                <button type="button" wire:click="previousStep"
                                    class="inline-flex min-h-[44px] items-center rounded-xl px-4 text-sm font-medium text-slate-600 hover:bg-slate-50">
                                    Retour
                                </button>
                            @endif

                            @if ($stepIndex < $this->stepCount() - 1)
                                <button type="button" wire:click="nextStep"
                                    class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-xl bg-slate-900 px-5 text-sm font-medium text-white transition hover:bg-slate-800">
                                    Continuer
                                </button>
                            @endif
                        </div>
                    @endif
                </section>

                {{--
                    LA CARTE DU TRAJET, juste après les questions qui posent ses deux points.

                    Elle vient APRÈS, jamais avant : elle montre ce qui a été répondu et permet de
                    l'affiner. Placée au-dessus, elle demanderait au client de désigner sur une
                    carte vide un départ qu'il n'a pas encore nommé — le geste le plus lent des
                    deux, imposé avant le plus rapide.

                    Elle se rend nulle d'elle-même hors des métiers de trajet.
                --}}
                @include('livewire.order-engine.partials.route-map')

                {{-- La photo est un RACCOURCI, offert avant l'adresse : elle remplace des questions,
                     elle ne s'ajoute pas à la file. --}}
                @include('livewire.order-engine.partials.photos')

                {{-- L'adresse vient APRÈS les questions : elle récompense, elle ne filtre pas. --}}
                @include('livewire.order-engine.partials.address-availability')

                {{-- Et juste après l'adresse, la question qui décide de QUI ouvrira la porte. --}}
                @include('livewire.order-engine.partials.beneficiary')

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
                                        <x-money :amount="(float) ($line['min_cents'] / 100)" :decimals="0" />
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

    {{--
        Le rattrapage du panier, quand le cookie a disparu.
    
        Le cookie de session reste la voie normale : il est `httpOnly`, donc hors de portée d'une XSS.
        Ce qui vit ici est une clé BORNÉE — hachée en base, tournante à chaque usage, expirante — et
        non le jeton de session recopié en clair.
    --}}
    <div
        data-cx-order-recovery
        hidden
        x-data="{
            cle: 'cx-order-recovery',
            emise: @js($this->recoveryKey),
            /*
             * Deux gestes, un seul echange avec le serveur dans le cas courant.
             *
             * Le serveur vient d'emettre une cle : on la RANGE. Il n'en a pas emis et on en a une
             * en reserve : on la PRESENTE, il rouvre le panier et rend une cle neuve.
             *
             * La cle tourne a chaque usage : celle qui dort ici ne sert qu'une fois. C'est ce qui
             * la distingue d'un second jeton de session laisse a la portee de tout script injecte.
             */
            boot() {
                let gardee = null;

                try {
                    gardee = window.localStorage.getItem(this.cle);
                } catch (e) {
                    // Navigation privee, stockage refuse : le parcours marche sans, on n'insiste pas.
                    return;
                }

                if (this.emise) {
                    try { window.localStorage.setItem(this.cle, this.emise); } catch (e) {}
                    return;
                }

                if (gardee) {
                    $wire.recoverDraft(gardee);
                }
            },
        }"
        x-init="boot()"
    ></div>
</div>

{{-- Les scripts des morceaux conditionnels, déclarés au premier rendu. Voir le morceau. --}}
@include('livewire.order-engine.partials.scripts')

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


