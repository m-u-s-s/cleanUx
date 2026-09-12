@props(['nom', 'alt' => '', 'hative' => false])

{{-- Une illustration de l'accueil : AVIF d'abord, WebP en repli.
     `hative` pour un bandeau visible dès la bascule ; les autres attendent
     d'approcher de l'écran. --}}
<picture {{ $attributes->merge(['class' => 'cx-arg__image']) }}>
    <source srcset="{{ asset('images/accueil/'.$nom.'.avif') }}" type="image/avif">
    <img src="{{ asset('images/accueil/'.$nom.'.webp') }}"
         alt="{{ $alt }}"
         width="1200" height="800"
         loading="{{ $hative ? 'eager' : 'lazy' }}"
         decoding="async"
         @if ($alt === '') aria-hidden="true" @endif>
</picture>
