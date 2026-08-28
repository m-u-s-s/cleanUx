@props([
    'text' => '',
])

@php
    // Le texte s'ecrit lettre par lettre : un mot = un bloc insecable, une lettre = son rang.
    $texte = trim((string) preg_replace('/\s+/u', ' ', (string) $text));
    $total = mb_strlen($texte);

    $mots = [];
    $rang = 0;

    foreach ($texte === '' ? [] : explode(' ', $texte) as $mot) {
        $lettres = [];

        foreach (mb_str_split($mot) as $lettre) {
            $lettres[] = ['c' => $lettre, 'i' => $rang++];
        }

        $mots[] = $lettres;
        // L'espace consomme un temps de frappe, sinon le mot suivant demarre trop tot.
        $rang++;
    }
@endphp

<span {{ $attributes->class('cx-typed') }} style="--cx-typed-n:{{ $total }}">
    <span class="sr-only">{{ $texte }}</span>
    <span class="cx-typed__ink" aria-hidden="true">@foreach ($mots as $mot)<span class="cx-typed__word">@foreach ($mot as $lettre)<span class="cx-typed__char" style="--cx-typed-i:{{ $lettre['i'] }}">{{ $lettre['c'] }}</span>@endforeach@if ($loop->last)<span class="cx-typed__caret"></span>@endif</span>@if (! $loop->last){{ ' ' }}@endif@endforeach</span>
</span>
