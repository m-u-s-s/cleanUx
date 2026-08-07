@props([
    'height' => 'h-4',
    'width' => 'w-full',
    'rounded' => 'rounded-xl',
])

<div {{ $attributes->merge(['class' => "brio-skeleton {$height} {$width} {$rounded}"]) }}></div>
