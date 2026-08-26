@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    /*
        `for` MANQUAIT, ET AUCUN APPELANT NE POUVAIT LE FOURNIR. Le composant rendait un
        `<label>` que rien ne rattachait au champ pose dans sa fente : au lecteur d'ecran,
        c'etait du texte a cote d'un champ anonyme.
    */
    'for' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    @if($label)
        <label @if($for) for="{{ $for }}" @endif class="text-sm font-semibold text-slate-700">{{ $label }}</label>
    @endif

    {{ $slot }}

    @if($hint)
        <p class="text-xs leading-5 text-slate-500">{{ $hint }}</p>
    @endif

    @if($error)
        <p class="text-xs font-medium text-red-600">{{ $error }}</p>
    @endif
</div>
