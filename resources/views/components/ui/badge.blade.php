@props([
    'label' => null,
    'tone' => 'neutral',
    'icon' => null,
])

@php
    $classes = match($tone) {
        'amber' => 'ui-badge ui-badge-amber',
        'green' => 'ui-badge ui-badge-green',
        'blue' => 'ui-badge ui-badge-blue',
        'red' => 'ui-badge ui-badge-red',
        default => 'ui-badge ui-badge-neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if($icon)
        <span class="text-[13px] leading-none">{{ $icon }}</span>
    @endif
    <span>{{ $label ?? $slot }}</span>
</span>
