@props([
    'tone' => 'amber',   {{-- amber | cyan | violet (glow accent) --}}
    'icon' => null,      {{-- x-ui.icon name --}}
    'position' => '',    {{-- raw CSS for placement, e.g. "top:2rem; right:1rem;" --}}
    'delay' => '0s',     {{-- float animation offset so cards drift out of sync --}}
])
{{-- Procedural glass "mock UI" card for the floating media layers.
     Pure CSS/SVG — no copyrighted assets. Continuous float (.cx-lux-float) is
     paused by GSAP during the entrance, then resumes (see luxury-hero.js). --}}
<div class="cx-lux-card cx-lux-float cx-lux-card--{{ $tone }}"
     style="{{ $position }} animation-delay: {{ $delay }};">
    <div class="cx-lux-card__row">
        @if ($icon)
            <span class="cx-lux-card__icon"><x-ui.icon :name="$icon" class="w-5 h-5" /></span>
        @endif
        <div class="min-w-0">
            {{ $slot }}
        </div>
    </div>
</div>
