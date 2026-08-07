@props([
    'title' => null,
    'subtitle' => null,
    'padding' => 'p-5 md:p-6',
    'muted' => false,
])

<div {{ $attributes->merge(['class' => ($muted ? 'brio-card-muted ' : 'brio-card ') . $padding]) }}>
    @if($title || $subtitle)
        <div class="mb-4">
            @if($title)
                <h3 class="brio-section-title">{{ $title }}</h3>
            @endif
            @if($subtitle)
                <p class="brio-section-subtitle">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
