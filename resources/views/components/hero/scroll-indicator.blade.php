@props([
    'target' => null,          {{-- anchor to scroll to, e.g. "#decouvrir" --}}
    'label' => 'Découvrir',
])
{{-- Animated scroll indicator (mouse + wheel). Motion fades it out on scroll
     (see luxury-hero.js). Falls back to a plain link without JS. --}}
<a @if ($target) href="{{ $target }}" @endif
   class="cx-lux-scroll" aria-label="{{ $label }}">
    <span class="cx-lux-scroll__label">{{ $label }}</span>
    <span class="cx-lux-scroll__mouse" aria-hidden="true">
        <span class="cx-lux-scroll__wheel"></span>
    </span>
</a>
