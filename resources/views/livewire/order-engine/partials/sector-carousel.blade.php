{{--
    Carrousel de secteurs.

    Défilement natif avec `scroll-snap` : l'inertie, le rebond et le geste au doigt sont ceux du
    système. Toute réimplémentation en JavaScript serait moins fluide et moins accessible — et ce
    sont exactement les deux choses qu'on cherche ici.

    La barre de défilement est masquée mais le défilement reste : les dégradés sur les bords
    signalent qu'il reste du contenu, ce qu'une barre absente ne dit plus.
--}}
<section
    x-data="{
        atStart: true,
        atEnd: false,
        track: null,
        init() {
            this.track = $refs.track;
            this.measure();
            this.track.addEventListener('scroll', () => this.measure(), { passive: true });
        },
        measure() {
            const t = this.track;
            this.atStart = t.scrollLeft <= 2;
            this.atEnd = t.scrollLeft + t.clientWidth >= t.scrollWidth - 2;
        },
        page(direction) {
            this.track.scrollBy({ left: direction * this.track.clientWidth * 0.8, behavior: 'smooth' });
        },

        /*
         * Tirer la bande à la souris.
         *
         * Le doigt a déjà l'inertie du système et le clavier a ses flèches ; la souris n'avait
         * que les deux boutons de pagination, sur une bande qui invite visiblement à être tirée.
         *
         * On n'intervient QUE pour la souris (`pointerType`) : capter aussi le tactile
         * réimplémenterait en JavaScript une inertie que le système fait mieux.
         */
        dragging: false,
        startX: 0,
        startScroll: 0,
        moved: 0,

        grab(event) {
            if (event.pointerType !== 'mouse' || event.button !== 0) {
                return;
            }

            this.dragging = true;
            this.moved = 0;
            this.startX = event.clientX;
            this.startScroll = this.track.scrollLeft;
        },

        drag(event) {
            if (! this.dragging) {
                return;
            }

            const delta = event.clientX - this.startX;
            this.moved = Math.abs(delta);
            this.track.scrollLeft = this.startScroll - delta;
        },

        release(event) {
            if (! this.dragging) {
                return;
            }

            this.dragging = false;

            /*
             * Un vrai glissement ne doit pas VALIDER la carte sous le curseur : sans cette garde,
             * tirer la bande ouvrirait le secteur où l'on relâche, et le client se retrouverait
             * dans un questionnaire qu'il n'a pas demandé.
             */
            if (this.moved > 5) {
                event.preventDefault();
                event.stopPropagation();
            }
        },
    }"
    class="relative"
    aria-roledescription="carrousel"
    aria-label="Secteurs d’activité"
>
    {{-- Dégradés de bord : ils disent « il y a la suite », là où la barre masquée ne dit plus rien. --}}
    <div class="pointer-events-none absolute inset-y-0 left-0 z-10 w-8 bg-gradient-to-r from-white to-transparent transition-opacity"
        x-bind:class="atStart ? 'opacity-0' : 'opacity-100'" aria-hidden="true"></div>
    <div class="pointer-events-none absolute inset-y-0 right-0 z-10 w-8 bg-gradient-to-l from-white to-transparent transition-opacity"
        x-bind:class="atEnd ? 'opacity-0' : 'opacity-100'" aria-hidden="true"></div>

    <ul
        x-ref="track"
        class="flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        role="list"
        x-on:pointerdown="grab($event)"
        x-on:pointermove="drag($event)"
        x-on:pointerup.capture="release($event)"
        x-on:pointerleave="dragging = false"
        x-bind:class="dragging ? 'cursor-grabbing select-none' : 'lg:cursor-grab'"
        {{-- Home et End sur la piste : au clavier, atteindre le dernier secteur ne doit pas demander douze flèches. --}}
        x-on:keydown.home.prevent="$refs.track.scrollTo({ left: 0, behavior: 'smooth' })"
        x-on:keydown.end.prevent="$refs.track.scrollTo({ left: $refs.track.scrollWidth, behavior: 'smooth' })"
    >
        @foreach ($this->sectors as $sector)
            <li class="snap-start shrink-0" wire:key="sector-{{ $sector->id }}">
                <button
                    type="button"
                    wire:click="selectSector({{ $sector->id }})"
                    @class([
                        'flex h-full w-[172px] flex-col items-start gap-2 rounded-2xl border p-4 text-left transition',
                        'focus-visible:outline-2 focus-visible:outline-offset-2',
                        'border-transparent text-white' => $sectorId === $sector->id,
                        'border-slate-200 bg-white hover:border-slate-300' => $sectorId !== $sector->id,
                    ])
                    @if ($sectorId === $sector->id)
                        {{-- Le SEUL endroit du produit où la couleur est saturée. --}}
                        style="background-color: {{ $sector->accent_color ?? '#0f172a' }}"
                    @endif
                    aria-current="{{ $sectorId === $sector->id ? 'true' : 'false' }}"
                >
                    {{--
                        L'image de couverture, PARESSEUSEMENT.

                        `cover_image_path` existait en base sans jamais être affiché. Cet écran est
                        celui dont dépend le LCP : charger d'emblée une image par secteur
                        retarderait le premier rendu utile pour des cartes qu'on ne verra
                        peut-être jamais, le carrousel défilant à l'horizontale.

                        Pas de balise vide quand il n'y a pas d'image : une image cassée est pire
                        que pas d'image.
                    --}}
                    @if ($sector->cover_image_path)
                        <img src="{{ Storage::disk('public')->url($sector->cover_image_path) }}"
                            alt=""
                            aria-hidden="true"
                            loading="lazy"
                            decoding="async"
                            class="mb-1 h-20 w-full rounded-xl object-cover">
                    @else
                        {{-- À défaut d'image, l'icône du secteur : la carte ne reste pas muette. --}}
                        <span data-sector-icon class="mb-1 flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-600"
                            aria-hidden="true">
                            <x-ui.icon :name="$sector->icon ?: 'circle'" class="h-5 w-5" />
                        </span>
                    @endif

                    <span class="text-[15px] font-semibold leading-snug">{{ $sector->name }}</span>

                    @if ($sector->tagline)
                        <span @class([
                            'text-xs leading-snug',
                            'text-white/80' => $sectorId === $sector->id,
                            'text-slate-500' => $sectorId !== $sector->id,
                        ])>{{ $sector->tagline }}</span>
                    @endif

                    {{-- Un signal vivant plutôt qu'un badge décoratif : la confiance vient de ce qui est vrai. --}}
                    <span @class([
                        'mt-auto pt-2 text-xs font-medium tabular-nums',
                        'text-white/90' => $sectorId === $sector->id,
                        'text-slate-600' => $sectorId !== $sector->id,
                    ])>
                        @php($pros = (int) ($sector->active_providers_count ?? 0))
                        @if ($pros > 0)
                            {{--
                                Le compte des professionnels, SANS promesse de proximité : aucune
                                adresse n'est connue à cet écran. « près de chez vous » appartient
                                au bloc d'adresse, qui le vérifie avant de l'écrire.
                            --}}
                            {{ $pros }} professionnel{{ $pros > 1 ? 's' : '' }}
                        @else
                            {{-- Jamais « 0 professionnel » : un secteur annoncé vide ne s'ouvre
                                 pas, alors qu'il porte peut-être un métier commandable demain. --}}
                            {{ $sector->trades_count }} métier{{ $sector->trades_count > 1 ? 's' : '' }}
                        @endif
                    </span>
                </button>
            </li>
        @endforeach
    </ul>

    {{-- Flèches sur grand écran seulement : au doigt, elles n'apportent rien et volent de la place. --}}
    <div class="pointer-events-none absolute inset-y-0 left-0 right-0 hidden items-center justify-between lg:flex">
        <button type="button" x-on:click="page(-1)" x-show="!atStart" aria-label="Secteurs précédents"
            class="pointer-events-auto -ml-4 flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white shadow-sm">‹</button>
        <button type="button" x-on:click="page(1)" x-show="!atEnd" aria-label="Secteurs suivants"
            class="pointer-events-auto -mr-4 flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white shadow-sm">›</button>
    </div>
</section>
