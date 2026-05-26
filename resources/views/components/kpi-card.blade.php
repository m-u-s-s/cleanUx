{{-- Full KPI card with glassmorphism background, icon, and gradient. Use for main dashboard stats. --}}
@props([
    'title',
    'value',
    'hint' => null,
    'tone' => 'slate',
    'icon' => null,
    'heroicon' => null,
])

@php
    $toneClasses = match($tone) {
        'amber' => 'text-amber-600 bg-amber-50 border-amber-100',
        'red' => 'text-red-600 bg-red-50 border-red-100',
        'orange' => 'text-orange-600 bg-orange-50 border-orange-100',
        'rose' => 'text-rose-600 bg-rose-50 border-rose-100',
        'blue' => 'text-brand-700 bg-brand-50 border-brand-100',
        'green' => 'text-emerald-700 bg-emerald-50 border-emerald-100',
        default => 'text-slate-700 bg-slate-50 border-slate-100',
    };
@endphp

<div {{ $attributes->merge(['class' => 'cu-kpi cu-glass cu-gradient-subtle']) }}>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="cu-kpi-label">{{ $title }}</p>
            <p class="cu-kpi-value">{{ $value }}</p>
        </div>

        @if($heroicon)
            <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border {{ $toneClasses }}">
                <x-ui.icon :name="$heroicon" class="w-5 h-5" />
            </div>
        @elseif($icon)
            <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border text-lg {{ $toneClasses }}">
                {{ $icon }}
            </div>
        @endif
    </div>

    @if($hint)
        <p class="cu-kpi-hint">{{ $hint }}</p>
    @endif
</div>
