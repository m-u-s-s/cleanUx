@props([
    'title',
    'subtitle' => null,
    'actionLabel' => null,
    'actionHref' => null,
])

<div class="cu-toolbar gap-3">
    <div>
        <h3 class="cu-section-title">{{ $title }}</h3>
        @if($subtitle)
            <p class="cu-section-subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @if($actionLabel && $actionHref)
        <a href="{{ $actionHref }}" class="text-sm font-semibold text-sky-600 transition hover:text-sky-700">
            {{ $actionLabel }}
        </a>
    @endif
</div>
