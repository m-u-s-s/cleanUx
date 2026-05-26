@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconPosition' => 'left',
    'loading' => false,
    'href' => null,
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 font-medium transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-brand-500 disabled:opacity-50 disabled:cursor-not-allowed select-none';

    $variants = [
        'primary' => 'bg-brand-600 text-white hover:bg-brand-700 active:bg-brand-800 shadow-soft-sm',
        'secondary' => 'bg-white text-surface-900 border border-surface-200 hover:bg-surface-50 active:bg-surface-100',
        'ghost' => 'bg-transparent text-surface-700 hover:bg-surface-100 active:bg-surface-200',
        'danger' => 'bg-danger-600 text-white hover:bg-danger-700 active:bg-danger-700 shadow-soft-sm',
        'success' => 'bg-success-600 text-white hover:bg-success-700 active:bg-success-700 shadow-soft-sm',
        'outline' => 'bg-transparent text-brand-700 border border-brand-300 hover:bg-brand-50 active:bg-brand-100',
        'link' => 'bg-transparent text-brand-600 hover:text-brand-700 hover:underline underline-offset-2',
        'amber' => 'bg-accent-amber text-slate-900 hover:bg-accent-amber-deep active:bg-amber-500 shadow-soft-sm font-semibold cu-glow-amber',
    ];

    $sizes = [
        'xs' => 'px-2.5 py-1 text-xs rounded-md',
        'sm' => 'px-3 py-1.5 text-sm rounded-lg',
        'md' => 'px-4 py-2 text-sm rounded-lg',
        'lg' => 'px-5 py-2.5 text-base rounded-xl',
        'xl' => 'px-6 py-3 text-base rounded-xl',
    ];

    $iconSizes = [
        'xs' => 'w-3.5 h-3.5',
        'sm' => 'w-4 h-4',
        'md' => 'w-4 h-4',
        'lg' => 'w-5 h-5',
        'xl' => 'w-5 h-5',
    ];

    $classes = trim($base . ' ' . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']));
    $iconClass = $iconSizes[$size] ?? $iconSizes['md'];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon && $iconPosition === 'left')
            <x-ui.icon :name="$icon" :class="$iconClass" />
        @endif
        {{ $slot }}
        @if ($icon && $iconPosition === 'right')
            <x-ui.icon :name="$icon" :class="$iconClass" />
        @endif
    </a>
@else
    <button type="{{ $type }}" @disabled($loading) {{ $attributes->merge(['class' => $classes]) }}>
        @if ($loading)
            <svg class="animate-spin {{ $iconClass }}" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
        @elseif ($icon && $iconPosition === 'left')
            <x-ui.icon :name="$icon" :class="$iconClass" />
        @endif
        {{ $slot }}
        @if (! $loading && $icon && $iconPosition === 'right')
            <x-ui.icon :name="$icon" :class="$iconClass" />
        @endif
    </button>
@endif
