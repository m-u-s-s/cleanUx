@props([
    'title' => null,
    'subtitle' => null,
])

<div class="cu-card overflow-hidden">
    @if($title || $subtitle)
        <div class="border-b border-slate-200/80 px-5 py-4 md:px-6">
            @if($title)
                <h3 class="cu-section-title">{{ $title }}</h3>
            @endif
            @if($subtitle)
                <p class="cu-section-subtitle">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    <div class="overflow-x-auto">
        {{ $slot }}
    </div>
</div>
