@props([
    'title' => 'Aucun résultat',
    'message' => 'Aucune donnée disponible pour le moment.',
    'icon' => '✨',
    'tone' => 'default',
])

@php
    $toneClasses = match($tone) {
        'amber' => 'ui-empty ui-empty-amber',
        default => 'ui-empty',
    };
@endphp

<div {{ $attributes->merge(['class' => $toneClasses]) }}>
    <div class="ui-empty-icon">{{ $icon }}</div>
    <h3 class="mt-4 text-lg font-bold text-slate-900">{{ $title }}</h3>
    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">{{ $message }}</p>

    @if(trim((string) $slot))
        <div class="mt-5 flex flex-wrap justify-center gap-3">
            {{ $slot }}
        </div>
    @endif
</div>
