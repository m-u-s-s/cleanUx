{{--
    ROTATION À 360° PAR PHOTOS — le format qu'emploient les grandes agences.

    L'ORDRE DES IMAGES EST LE SENS DE ROTATION : `position` les range, et deux images interverties
    feraient sauter la voiture en arrière au milieu du geste. C'est pour cela que
    `NosLocationsCenter::remplacerLaRotation()` remplace la séquence entière au lieu d'y ajouter.

    Aucune bibliothèque : le glisser tient en quinze lignes d'Alpine, et une dépendance
    supplémentaire pour cela coûterait plus cher qu'elle ne rapporte.

    LA PREMIÈRE IMAGE EST CHARGÉE, LES AUTRES SONT DIFFÉRÉES. Vingt-quatre photos en `eager`
    retarderaient l'affichage de la fiche pour un geste que le client ne fera peut-être pas.
--}}
@props(['images', 'alt' => ''])

<div
    x-data="{
        index: 0,
        total: {{ count($images) }},
        dragging: false,
        lastX: 0,
        seuil: 8,
        demarrer(e) {
            this.dragging = true;
            this.lastX = e.clientX ?? 0;
        },
        bouger(e) {
            if (! this.dragging || this.total < 2) return;
            const x = e.clientX ?? 0;
            const delta = x - this.lastX;
            if (Math.abs(delta) < this.seuil) return;
            const pas = delta > 0 ? 1 : -1;
            this.index = (this.index + pas + this.total) % this.total;
            this.lastX = x;
        },
        arreter() { this.dragging = false; },
    }"
    @pointerdown="demarrer($event)"
    @pointermove="bouger($event)"
    @pointerup.window="arreter()"
    @pointercancel.window="arreter()"
    class="relative select-none overflow-hidden rounded-3xl bg-slate-100 dark:bg-slate-800"
    style="touch-action: pan-y;"
    role="group"
    aria-label="Vue à 360 degrés du véhicule, faites glisser pour tourner"
>
    @foreach ($images as $i => $image)
        <img
            src="{{ $image->url() }}"
            alt="{{ $i === 0 ? $alt : '' }}"
            @if ($i > 0) aria-hidden="true" @endif
            loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
            x-show="index === {{ $i }}"
            x-cloak
            class="aspect-[4/3] w-full object-cover"
        >
    @endforeach

    <div class="pointer-events-none absolute inset-x-0 bottom-3 flex justify-center">
        <span class="rounded-full bg-black/60 px-3 py-1 text-xs font-semibold text-white">
            Faites glisser pour tourner
        </span>
    </div>

    {{-- LE CLAVIER AUSSI : sans ces deux boutons, la vue serait réservée à la souris et au doigt. --}}
    <div class="absolute inset-y-0 left-0 flex items-center">
        <button type="button" @click="index = (index - 1 + total) % total"
            class="m-2 min-h-[44px] min-w-[44px] rounded-full bg-white/80 text-slate-700 shadow"
            aria-label="Tourner à gauche">&lsaquo;</button>
    </div>
    <div class="absolute inset-y-0 right-0 flex items-center">
        <button type="button" @click="index = (index + 1) % total"
            class="m-2 min-h-[44px] min-w-[44px] rounded-full bg-white/80 text-slate-700 shadow"
            aria-label="Tourner à droite">&rsaquo;</button>
    </div>
</div>
