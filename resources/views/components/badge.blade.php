@props(['status'])

@php
    $normalized = strtolower((string) $status);

    $config = match($normalized) {
        'confirme' => ['classes' => 'bg-green-100 text-green-700 border-green-200', 'label' => 'Confirmé'],
        'refuse' => ['classes' => 'bg-red-100 text-red-700 border-red-200', 'label' => 'Refusé'],
        'en_attente' => ['classes' => 'bg-yellow-100 text-yellow-700 border-yellow-200', 'label' => 'En attente'],
        'en_route' => ['classes' => 'bg-blue-100 text-blue-700 border-blue-200', 'label' => 'En route'],
        'sur_place' => ['classes' => 'bg-indigo-100 text-indigo-700 border-indigo-200', 'label' => 'Sur place'],
        'termine' => ['classes' => 'bg-emerald-100 text-emerald-700 border-emerald-200', 'label' => 'Terminé'],
        default => ['classes' => 'bg-gray-100 text-gray-700 border-gray-200', 'label' => ucfirst(str_replace('_', ' ', $normalized))],
    };
@endphp

<span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-semibold {{ $config['classes'] }}">
    {{ $config['label'] }}
</span>