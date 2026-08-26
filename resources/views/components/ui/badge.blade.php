@props([
    'label' => null,
    'tone' => 'neutral',
    'icon' => null,
])

@php
    /*
        LE COMPOSANT PARLAIT COULEUR, LE SYSTEME PARLE SENS.

        Il emettait `ui-badge-amber|green|blue|red` ; le CSS ne definit que
        `ui-badge-neutral|brand|success|warning|danger|info`. QUATRE TONS SUR CINQ ne
        correspondaient a aucune regle : le badge sortait sans couleur, et le seul appel
        de production (`browse-companies`) portait justement `tone="blue"`.

        Nommer la couleur oblige a choisir la teinte a chaque appel ; nommer le SENS laisse
        le theme decider, et le mode sombre suit sans qu'on y pense.
    */
    $classes = match ($tone) {
        'warning' => 'ui-badge ui-badge-warning',
        'success' => 'ui-badge ui-badge-success',
        'info' => 'ui-badge ui-badge-info',
        'danger' => 'ui-badge ui-badge-danger',
        'brand' => 'ui-badge ui-badge-brand',
        default => 'ui-badge ui-badge-neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if($icon)
        <span class="text-[13px] leading-none">{{ $icon }}</span>
    @endif
    <span>{{ $label ?? $slot }}</span>
</span>
