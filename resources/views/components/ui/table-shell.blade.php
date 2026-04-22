@props([
    'title' => null,
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'ui-card overflow-hidden']) }}>
    @if($title || $subtitle || isset($actions))
        <div class="ui-card-header px-5 pt-5 md:px-6 md:pt-6">
            <div>
                @if($title)
                    <h3 class="ui-card-title">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="ui-card-subtitle">{{ $subtitle }}</p>
                @endif
            </div>
            @if(isset($actions))
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endif
        </div>
    @endif

    <div class="overflow-x-auto">
        {{ $slot }}
    </div>
</div>
