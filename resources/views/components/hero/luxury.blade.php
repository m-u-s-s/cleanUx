@props([
    'scrollTarget' => null,    {{-- anchor for the scroll indicator --}}
    'scrollLabel' => 'Découvrir',
])
{{-- ============================================================================
     Luxury hero (dark cinematic) — reusable shell.
     Slots:
       $eyebrow  → small status pill (use <x-hero.eyebrow>)
       $title    → headline markup (wrap lines in <span class="cx-line">)
       $slot     → lead paragraph text
       $actions  → CTA buttons
       $trust    → trust / reassurance line
       $media    → floating glass cards (use <x-hero.floating-card>)
     Animation + WebGL handled by resources/js/luxury-hero.js.
     ========================================================================= --}}
<section {{ $attributes->merge(['class' => 'cx-lux-hero']) }} data-lux-hero data-cx-parallax-root>
    {{-- WebGL particle background — React Three Fiber island mounts here
         (resources/js/hero-r3f.jsx). Empty by default → CSS gradient fallback
         under reduced-motion / no-WebGL. --}}
    <div class="cx-lux-hero__bg" data-lux-r3f aria-hidden="true"></div>
    {{-- Gradient overlays: vignette + brand wash + top/bottom blend. --}}
    <div class="cx-lux-hero__overlay" aria-hidden="true"></div>
    <div class="cx-lux-hero__grain" aria-hidden="true"></div>

    <div class="cx-lux-hero__inner">
        {{-- Content column (subtle reverse parallax). --}}
        <div class="cx-lux-hero__content cx-parallax-layer" data-cx-parallax="-8">
            @isset($eyebrow)
                <div data-lux-anim>{{ $eyebrow }}</div>
            @endisset

            @isset($title)
                {{-- data-lux-anim conservé pour la garde anti-FOUC (.lux-js => opacity:0)
                     et la visibilité en reduced-motion. data-lux-title : luxury-hero.js
                     l'exclut de l'entrance générique et lui applique la révélation
                     cinématique ligne par ligne (moteur text-reveal-core). --}}
                <h1 class="cx-lux-title" data-lux-anim data-lux-title>{{ $title }}</h1>
            @endisset

            @if ($slot->isNotEmpty())
                <p class="cx-lux-lead" data-lux-anim>{{ $slot }}</p>
            @endif

            @isset($actions)
                <div class="cx-lux-actions" data-lux-anim>{{ $actions }}</div>
            @endisset

            @isset($trust)
                <div class="cx-lux-trust" data-lux-anim>{{ $trust }}</div>
            @endisset

            {{-- Preuve compacte visible UNIQUEMENT < lg (où le stage des cartes
                 flottantes est masqué) : conserve la preuve sociale sur mobile/tablette
                 sans réintroduire les cartes en absolu. Masqué en desktop (carte complète). --}}
            @isset($proof)
                <div class="cx-lux-proof" data-lux-anim>{{ $proof }}</div>
            @endisset
        </div>

        {{-- Floating media layers (deeper parallax). --}}
        @isset($media)
            <div class="cx-lux-stage cx-parallax-layer" data-cx-parallax="22" aria-hidden="true">
                {{ $media }}
            </div>
        @endisset
    </div>

    <x-hero.scroll-indicator :target="$scrollTarget" :label="$scrollLabel" />
</section>
