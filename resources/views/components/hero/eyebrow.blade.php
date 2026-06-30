{{-- Luxury hero eyebrow pill (animated status dot + uppercase label).
     Usage: <x-hero.eyebrow>Marketplace multi-métiers</x-hero.eyebrow> --}}
<span {{ $attributes->merge(['class' => 'cx-lux-eyebrow']) }}>
    <span class="cx-lux-eyebrow__dot" aria-hidden="true"></span>
    {{ $slot }}
</span>
