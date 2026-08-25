@props(['active'])

{{--
    LE LIBELLE NE REVIENT PLUS A LA LIGNE.

    Sans `whitespace-nowrap`, « Trouver un prestataire » se cassait en deux lignes, la barre
    gagnait en hauteur et les liens suivants debordaient sur le bloc de droite : « Modules »
    chevauchait le selecteur de langue a 1440 px.

    L'etat actif porte l'accent de la marque, pas un indigo qui n'appartient a rien.
--}}
@php
$base = 'brio-lien-nav inline-flex items-center whitespace-nowrap px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition';
$classes = ($active ?? false) ? $base.' brio-lien-nav-actif' : $base;
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
