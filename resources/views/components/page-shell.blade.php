@props([
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'actions' => null,
])

<div class="brio-hero">
    <div class="relative brio-toolbar gap-4">
        <div class="max-w-3xl">
            @if($eyebrow)
                <span class="brio-eyebrow">{{ $eyebrow }}</span>
            @endif

            {{-- LA COQUILLE DE PAGE PORTE LE TITRE DE LA PAGE : c est un h1, pas un h2. Vingt-deux
                 vues en dependent, et aucune n en declarait un. Neutre a l affichage : la seule
                 regle par element est `h1, h2, h3, h4 { color }`, et Tailwind remet la taille a
                 `inherit` — ce sont les classes qui la portent. --}}
            <h1 class="mt-3 text-2xl font-black tracking-tight text-slate-900 md:text-3xl">
                {{ $title }}
            </h1>

            @if($subtitle)
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 md:text-base">
                    {{ $subtitle }}
                </p>
            @endif
        </div>

        @if($actions)
            <div class="relative flex flex-wrap gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>

    @if(trim((string) $slot))
        <div class="relative mt-6">
            {{ $slot }}
        </div>
    @endif
</div>
