@props([
    'href' => null,
    'variant' => 'secondary',
    'size' => 'md',
    'icon' => null,
    'type' => 'button',
    'target' => null,
])

@php
    $variantClasses = match($variant) {
        'primary' => 'ui-action ui-action-primary',
        'danger' => 'ui-action ui-action-danger',
        'amber' => 'ui-action ui-action-amber',
        'ghost' => 'ui-action ui-action-ghost',
        default => 'ui-action ui-action-secondary',
    };

    $sizeClasses = match($size) {
        'sm' => 'px-3 py-2 text-xs rounded-xl',
        'lg' => 'px-5 py-3 text-sm rounded-2xl',
        default => 'px-4 py-2.5 text-sm rounded-xl',
    };

    $classes = trim($variantClasses.' '.$sizeClasses);
@endphp

@if($href)
    <a href="{{ $href }}" target="{{ $target }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)
            <span class="text-base leading-none">{{ $icon }}</span>
        @endif
        <span>{{ $slot }}</span>
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)
            <span class="text-base leading-none">{{ $icon }}</span>
        @endif
        <span>{{ $slot }}</span>
    </button>
@endif
