@props(['nom', 'alt' => ''])

{{-- Une illustration de l'accueil : AVIF d'abord, WebP en repli.
     Toutes sont sous la ligne de flottaison, donc chargées paresseusement. --}}
<picture class="cx-arg__image">
    <source srcset="{{ asset('images/accueil/'.$nom.'.avif') }}" type="image/avif">
    <img src="{{ asset('images/accueil/'.$nom.'.webp') }}"
         alt="{{ $alt }}"
         width="1200" height="800"
         loading="lazy" decoding="async">
</picture>
