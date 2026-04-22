@props([
    'title' => null,
    'subtitle' => null,
    'padding' => 'p-5 md:p-6',
    'muted' => false,
])

<div {{ $attributes->merge(['class' => ($muted ? 'cu-card-muted ' : 'cu-card ') . $padding]) }}>
    @if($title || $subtitle)
        <div class="mb-4">
            @if($title)
                <h3 class="cu-section-title">{{ $title }}</h3>
            @endif
            @if($subtitle)
                <p class="cu-section-subtitle">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
