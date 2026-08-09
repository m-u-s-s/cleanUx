@props([
    /** 'client' | 'provider' | null — null = déduit de l'utilisateur connecté. */
    'space' => null,
    /** Taille en pixels du côté de la vignette. */
    'size' => 36,
    /** Afficher le nom à côté de la marque. */
    'label' => false,
    /** Texte alternatif ; vide = image décorative (le nom voisin porte déjà l'information). */
    'alt' => null,
    /**
     * 'light' | 'dark' | null.
     *
     * `null` laisse le CSS trancher, ce qui convient aux espaces dotés d'une vraie bascule de
     * thème. UNE SURFACE DÉFINITIVEMENT SOMBRE DOIT FORCER, elle : le site public est noir en
     * permanence sans porter la classe `dark`, si bien que la règle automatique y servait la
     * version crème — un badge clair sur un fond noir, l'inverse de ce qui est voulu.
     */
    'variant' => null,
])

@php
    use App\Support\Brand\BrandMark;

    $espace = $space ?? BrandMark::spaceFor();
    $nom = BrandMark::label($espace);
    // Deux fois la taille demandée : les écrans à haute densité montrent une image floue sinon.
    // La taille est ramenée à une déclinaison EXISTANTE — un fichier calculé au hasard produit un
    // cadre vide, et un cadre vide ne fait échouer aucun test.
    $fichier = BrandMark::nearestSize((int) $size * 2);
    $texteAlternatif = $alt ?? ($label ? '' : $nom);
    $force = in_array($variant, ['light', 'dark'], true) ? $variant : null;
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    {{--
        LES DEUX VARIANTES SONT RENDUES, ET LE CSS EN MONTRE UNE.

        Le thème se choisit dans le NAVIGATEUR — bascule manuelle, préférence système, cache HTTP
        partagé. Résoudre clair/sombre côté serveur servirait donc l'image sombre à qui vient de
        passer en clair, et une page mise en cache figerait l'erreur pour tout le monde.

        `loading="eager"` : c'est la marque, elle est au-dessus de la ligne de flottaison sur chaque
        page. Un chargement différé la ferait apparaître après le reste, ce qui se voit.
    --}}
    @if ($force !== 'dark')
        <img src="{{ BrandMark::path($espace, 'light', $fichier) }}"
            alt="{{ $texteAlternatif }}"
            width="{{ $size }}" height="{{ $size }}"
            loading="eager" decoding="async"
            class="{{ $force === 'light' ? 'block' : 'block dark:hidden' }}"
            style="width:{{ $size }}px;height:{{ $size }}px">
    @endif

    @if ($force !== 'light')
        <img src="{{ BrandMark::path($espace, 'dark', $fichier) }}"
            alt="{{ $texteAlternatif }}"
            width="{{ $size }}" height="{{ $size }}"
            loading="eager" decoding="async"
            class="{{ $force === 'dark' ? 'block' : 'hidden dark:block' }}"
            style="width:{{ $size }}px;height:{{ $size }}px">
    @endif

    @if ($label)
        <span class="leading-tight">
            <span class="block font-black tracking-tight">{{ config('app.name', 'Brio') }}</span>
            @if ($slot->isNotEmpty())
                <span class="block text-[11px] uppercase tracking-[0.22em] opacity-70">{{ $slot }}</span>
            @endif
        </span>
    @endif
</span>
