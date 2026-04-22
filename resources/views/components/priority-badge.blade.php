@php
    $priority = $priority ?? 'normale';

    $classes = match ($priority) {
        'urgente' => 'bg-red-100 text-red-700 border-red-200',
        'haute' => 'bg-orange-100 text-orange-700 border-orange-200',
        'normale' => 'bg-blue-100 text-blue-700 border-blue-200',
        default => 'bg-gray-100 text-gray-700 border-gray-200',
    };

    $label = match ($priority) {
        'urgente' => 'Urgente',
        'haute' => 'Haute',
        'normale' => 'Normale',
        default => ucfirst(str_replace('_', ' ', $priority)),
    };
@endphp

<span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-semibold {{ $classes }}">
    @if($priority === 'urgente')
        🚨
    @elseif($priority === 'haute')
        ⚠️
    @else
        🔹
    @endif
    <span class="ml-1">{{ $label }}</span>
</span>