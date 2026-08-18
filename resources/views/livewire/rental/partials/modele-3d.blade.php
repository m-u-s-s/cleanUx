{{--
    MODÈLE 3D glTF — pour les véhicules dont l'administrateur a déposé un fichier.

    THREE.JS N'EST CHARGÉ QUE SI LA VUE ARRIVE À L'ÉCRAN. Ce dépôt a déjà payé cette leçon en
    sortant la 3D du chemin critique de la page d'accueil. Un import en tête de page ferait payer
    plusieurs centaines de kilo-octets à tout visiteur du catalogue, y compris à ceux dont la
    voiture n'a pas de modèle.

    LE REPLI EST ASSUMÉ : sans WebGL ou en cas d'échec, la photo reste. Un carré noir portant un
    message d'erreur serait pire que pas de 3D du tout.
--}}
@props(['modele', 'poster' => null, 'alt' => ''])

<div
    wire:ignore
    x-data="modele3dLocation(@js($modele->url()))"
    class="relative overflow-hidden rounded-3xl bg-slate-100 dark:bg-slate-800"
>
    <div x-ref="toile" class="aspect-[4/3] w-full" role="img"
         aria-label="Modèle 3D du véhicule : {{ $alt }}"></div>

    @if ($poster)
        <img src="{{ $poster->url() }}" alt="{{ $alt }}"
             x-show="! monte || echec" x-cloak
             class="absolute inset-0 aspect-[4/3] w-full object-cover">
    @endif

    <div class="pointer-events-none absolute inset-x-0 bottom-3 flex justify-center"
         x-show="monte && ! echec" x-cloak>
        <span class="rounded-full bg-black/60 px-3 py-1 text-xs font-semibold text-white">
            Faites glisser pour tourner &middot; molette pour zoomer
        </span>
    </div>
</div>
