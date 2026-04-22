@props([
    'title' => null,
    'subtitle' => null,
    'padding' => 'p-5 md:p-6',
    'tone' => 'default',
    'eyebrow' => null,
])

@php
    $toneClasses = match($tone) {
        'amber' => 'ui-card ui-card-amber',
        'danger' => 'ui-card ui-card-danger',
        'dark' => 'ui-card ui-card-dark',
        default => 'ui-card',
    };
@endphp

<div {{ $attributes->merge(['class' => trim($toneClasses.' '.$padding)]) }}>
    @if($title || $subtitle || $eyebrow || isset($actions))
        <div class="ui-card-header">
            <div class="min-w-0">
                @if($eyebrow)
                    <div class="mb-3">
                        <span class="ui-badge ui-badge-neutral">{{ $eyebrow }}</span>
                    </div>
                @endif

                @if($title)
                    <h3 class="ui-card-title">{{ $title }}</h3>
                @endif

                @if($subtitle)
                    <p class="ui-card-subtitle">{{ $subtitle }}</p>
                @endif
            </div>

            @if(isset($actions))
                <div class="flex flex-wrap items-center gap-2">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif

    {{ $slot }}

    @if(isset($footer))
        <div class="mt-5 pt-4 border-t border-slate-200/80">
            {{ $footer }}
        </div>
    @endif
</div>
