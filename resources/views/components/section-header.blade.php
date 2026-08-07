@props([
    'title',
    'subtitle' => null,
    'actionLabel' => null,
    'actionHref' => null,
])

<div class="brio-toolbar gap-3">
    <div>
        <h3 class="brio-section-title">{{ $title }}</h3>
        @if($subtitle)
            <p class="brio-section-subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @if($actionLabel && $actionHref)
        <a href="{{ $actionHref }}" class="text-sm font-semibold text-sky-600 transition hover:text-sky-700">
            {{ $actionLabel }}
        </a>
    @endif
</div>
