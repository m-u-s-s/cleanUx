@props([
    'title',
    'value',
    'hint' => null,
    'icon' => null,
    'tone' => 'slate',
    'trend' => null,
])

@php
    $toneClasses = match($tone) {
        'amber' => 'ui-stat-icon text-amber-700 bg-amber-50 border-amber-100',
        'red' => 'ui-stat-icon text-red-700 bg-red-50 border-red-100',
        'orange' => 'ui-stat-icon text-orange-700 bg-orange-50 border-orange-100',
        'rose' => 'ui-stat-icon text-rose-700 bg-rose-50 border-rose-100',
        'blue' => 'ui-stat-icon text-blue-700 bg-blue-50 border-blue-100',
        'green' => 'ui-stat-icon text-emerald-700 bg-emerald-50 border-emerald-100',
        default => 'ui-stat-icon text-slate-700 bg-slate-50 border-slate-100',
    };
@endphp

<div {{ $attributes->merge(['class' => 'ui-stat']) }}>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="ui-stat-label">{{ $title }}</p>
            <p class="ui-stat-value">{{ $value }}</p>
        </div>

        @if($icon)
            <div class="{{ $toneClasses }}">
                {{ $icon }}
            </div>
        @endif
    </div>

    @if($hint || $trend)
        <div class="mt-3 flex flex-wrap items-center gap-2">
            @if($hint)
                <p class="ui-stat-hint">{{ $hint }}</p>
            @endif
            @if($trend)
                <span class="ui-badge ui-badge-neutral">{{ $trend }}</span>
            @endif
        </div>
    @endif
</div>
