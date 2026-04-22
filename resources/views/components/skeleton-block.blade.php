@props([
    'height' => 'h-4',
    'width' => 'w-full',
    'rounded' => 'rounded-xl',
])

<div {{ $attributes->merge(['class' => "cu-skeleton {$height} {$width} {$rounded}"]) }}></div>
