@props([
    'title' => null,
    'subtitle' => null,
])

<div class="brio-card overflow-hidden">
    @if($title || $subtitle)
        <div class="border-b border-slate-200/80 px-5 py-4 md:px-6">
            @if($title)
                <h3 class="brio-section-title">{{ $title }}</h3>
            @endif
            @if($subtitle)
                <p class="brio-section-subtitle">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    <div class="overflow-x-auto">
        {{ $slot }}
    </div>
</div>
