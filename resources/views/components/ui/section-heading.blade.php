@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'ui-section-heading']) }}>
    <div class="min-w-0">
        @if($eyebrow)
            <div class="mb-3">
                <span class="ui-badge ui-badge-neutral">{{ $eyebrow }}</span>
            </div>
        @endif

        <h2 class="ui-section-title">{{ $title }}</h2>

        @if($subtitle)
            <p class="ui-section-subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @if(isset($actions))
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
