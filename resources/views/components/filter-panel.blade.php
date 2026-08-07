@props([
    'title' => null,
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'brio-card p-4 md:p-5']) }}>
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
