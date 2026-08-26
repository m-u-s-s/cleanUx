@props([
    'title',
    'value',
    'hint' => null,
    'icon' => null,
    'heroicon' => null,
    'tone' => 'slate',
    'trend' => null,
    /*
     * L'UNITE SE POSE A COTE DE LA VALEUR, PAS DEDANS.
     *
     * L'ecran de l'employe passait `$progression . '%'` : le pourcentage prenait alors la
     * taille et le poids du chiffre, et « 100% » debordait sur un ecran de 390px la ou
     * « 100 » tenait. La classe existait pour cela, sans appelant.
     */
    'unit' => null,
])

{{--
    LA CASE DE STATISTIQUE — les tons passent par les jetons, plus par la palette.

    Ce composant portait huit variantes écrites en couleurs Tailwind fixes : `bg-amber-50`,
    `text-red-700`, `border-brand-100`. Douze appels en héritaient, et aucun ne suivait le
    thème : sur la nuit, un fond `-50` reste un fond clair, et le texte `-700` posé dessus
    devient illisible dès que la surface, elle, s'assombrit.

    `.brio-stat*` disait exactement la même chose en jetons — et n'avait AUCUN appelant. Les
    deux systèmes ont vécu côte à côte : celui qui tenait la page ignorait le thème, celui qui
    le respectait n'était nulle part.

    Les noms de tons d'origine sont conservés (`amber`, `red`, `blue`…) : les renommer aurait
    obligé à toucher les douze appels pour un gain nul, et un ton inconnu tombe sur le neutre
    plutôt que de disparaître.
--}}
@php
    $tonBrio = match ($tone) {
        'amber', 'orange' => 'brio-stat-warn',
        'red', 'rose' => 'brio-stat-bad',
        'emerald', 'green' => 'brio-stat-good',
        'blue', 'brand' => 'brio-stat-accent',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => trim('brio-stat text-left '.$tonBrio)]) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="brio-stat-label !mt-0">{{ $title }}</p>
            <p class="brio-stat-value">{{ $value }}@if($unit)<span class="brio-stat-unit">{{ $unit }}</span>@endif</p>
        </div>

        {{-- L'icône prend la couleur du ton par héritage : elle en portait une seconde,
             qui pouvait contredire celle de la valeur. --}}
        @if($heroicon)
            <div class="brio-stat-icone" aria-hidden="true">
                <x-ui.icon :name="$heroicon" class="w-5 h-5" />
            </div>
        @elseif($icon)
            <div class="brio-stat-icone" aria-hidden="true">
                {{ $icon }}
            </div>
        @endif
    </div>

    @if($hint || $trend)
        <div class="mt-3 flex flex-wrap items-center gap-2">
            @if($hint)
                <p class="brio-stat-label !mt-0">{{ $hint }}</p>
            @endif
            @if($trend)
                {{--
                    LE SIGNE DONNE LA DIRECTION. Une pastille neutre disait « +23% » et
                    « -8% » de la meme facon : la seule chose qu'on regarde sur une
                    tendance — monte-t-elle ? — demandait de lire le caractere.

                    Ce qui ne commence ni par + ni par - reste neutre : « stable » n'est
                    ni une hausse ni une baisse.
                --}}
                @php($sens = str_starts_with(trim((string) $trend), '+') ? 'up' : (str_starts_with(trim((string) $trend), '-') ? 'down' : null))
                <span @class(['brio-kpi-trend', 'brio-kpi-trend-'.$sens => $sens, 'brio-chip' => ! $sens])>{{ $trend }}</span>
            @endif
        </div>
    @endif
</div>
