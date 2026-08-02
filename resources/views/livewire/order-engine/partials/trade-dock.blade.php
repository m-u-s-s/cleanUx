{{--
    Le dock de métiers — la seule audace de l'interface.

    La magnification suit la DISTANCE du curseur à chaque élément, pas un simple `:hover`. C'est
    toute la différence : au survol, un voisin grossit un peu, son voisin un peu moins, et
    l'ensemble ondule au lieu de sauter. Un `:hover` produirait un escalier.

    Trois règles tenues :

    — un seul `x-data` sur le conteneur, une seule écoute de `pointermove`, et l'écriture directe
      d'une variable CSS par élément. Aucune boucle d'animation à nettoyer, donc rien qui continue
      de tourner après le départ du pointeur ;

    — sur TACTILE, le dock est remplacé par une grille. Un effet de survol sur mobile est un bug,
      pas une fonctionnalité : le doigt n'a pas de position au repos ;

    — en mouvement réduit, la magnification disparaît au profit d'un simple changement de contour.
      L'information reste, le mouvement s'en va.

    Le focus clavier déclenche exactement la même magnification que le survol : la démonstration ne
    doit pas être réservée à la souris.
--}}
<div
    x-data="{
        touch: window.matchMedia('(hover: none)').matches,
        reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        /**
         * Décroissance par palier de voisinage : 1,55 / 1,3 / 1,12 / 1.
         *
         * L'exposant vaut 1,5 et non 2,2 : à 2,2 la courbe s'effondrait trop vite et les voisins
         * n'atteignaient que 1,23 et 1,05. L'onde était là, mais si plate qu'elle se lisait comme
         * un simple survol — or c'est justement l'ondulation qui distingue ce dock d'un `:hover`.
         * À 1,5 on retrouve 1,30 et 1,11, l'interpolation restant continue.
         */
        scaleFor(distance) {
            const step = 76;
            const t = Math.min(Math.abs(distance) / step, 3);
            if (t >= 3) return 1;
            return 1 + 0.55 * Math.pow(1 - t / 3, 1.5);
        },
        magnify(clientX) {
            if (this.touch || this.reduced) return;
            for (const item of $refs.dock.querySelectorAll('[data-dock-item]')) {
                const box = item.getBoundingClientRect();
                item.style.setProperty('--cx-scale', this.scaleFor(clientX - (box.left + box.width / 2)).toFixed(3));
            }
        },
        rest() {
            for (const item of $refs.dock.querySelectorAll('[data-dock-item]')) {
                item.style.setProperty('--cx-scale', 1);
            }
        },
    }"
    x-on:pointermove.passive="magnify($event.clientX)"
    x-on:pointerleave="rest()"
    x-on:focusin="magnify($event.target.getBoundingClientRect().left + $event.target.offsetWidth / 2)"
    x-on:focusout="rest()"
>
    <ul
        x-ref="dock"
        role="list"
        @class([
            'gap-3',
            'grid grid-cols-2' => true,
            'sm:flex sm:items-end sm:justify-start sm:gap-2' => true,
        ])
    >
        @foreach ($this->trades as $trade)
            <li wire:key="trade-{{ $trade->id }}" class="relative">
                <button
                    type="button"
                    data-dock-item
                    {{--
                        Transition d'élément partagé : le métier choisi MONTE devenir l'en-tête du
                        questionnaire au lieu de disparaître pour être remplacé. Le client garde le
                        fil de ce qu'il a choisi.

                        Le nom n'est porté que par l'élément survolé au moment du clic : deux
                        éléments partageant le même `view-transition-name` au même instant
                        annuleraient la transition entière.
                    --}}
                    x-on:click="
                        $el.style.viewTransitionName = 'cx-trade-choisi';
                        if (! document.startViewTransition || reduced) { $wire.selectTrade({{ $trade->id }}); return }
                        document.startViewTransition(() => $wire.selectTrade({{ $trade->id }}))
                    "
                    class="group relative flex min-h-[72px] w-full flex-col items-start justify-end gap-1 rounded-2xl border border-slate-200 bg-white p-3 text-left transition-[border-color,box-shadow] focus-visible:outline-2 focus-visible:outline-offset-2 sm:w-[104px] sm:items-center sm:text-center
                           motion-safe:sm:[transform:scale(var(--cx-scale,1))] motion-safe:sm:[transform-origin:bottom_center]
                           motion-safe:sm:[transition:transform_260ms_cubic-bezier(.22,1.2,.36,1)]
                           motion-reduce:focus-visible:border-slate-900 motion-reduce:hover:border-slate-900"
                    style="--cx-scale: 1"
                >
                    <span class="text-[15px] font-medium leading-tight text-slate-900 sm:text-sm">{{ $trade->name }}</span>

                    @if ($trade->short_description)
                        <span class="text-xs leading-snug text-slate-500 sm:hidden">{{ $trade->short_description }}</span>
                    @endif

                    {{--
                        La bulle du libellé, sur grand écran seulement : dans le dock, le nom est
                        tronqué faute de place, et c'est elle qui le rend lisible au survol.
                    --}}
                    <span class="pointer-events-none absolute -top-9 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-slate-900 px-2.5 py-1 text-xs font-medium text-white opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100 sm:block"
                        aria-hidden="true">{{ $trade->name }}</span>
                </button>
            </li>
        @endforeach
    </ul>
</div>
