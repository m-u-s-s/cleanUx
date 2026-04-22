@props([
    'title' => 'Aucun résultat',
    'message' => 'Aucune donnée disponible pour le moment.',
    'icon' => '✨',
])

<div class="cu-empty">
    <div class="cu-empty-icon">{{ $icon }}</div>
    <h3 class="mt-4 text-lg font-bold text-slate-900">{{ $title }}</h3>
    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">{{ $message }}</p>

    @if(trim((string) $slot))
        <div class="mt-5 flex justify-center">
            {{ $slot }}
        </div>
    @endif
</div>
