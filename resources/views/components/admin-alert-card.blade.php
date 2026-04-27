@props([
    'title',
    'items',
    'empty' => 'Aucune alerte.',
])

<div class="rounded-2xl border bg-white shadow-sm overflow-hidden">
    <div class="border-b p-4 flex items-center justify-between">
        <h3 class="font-semibold text-slate-900">{{ $title }}</h3>

        <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
            {{ $items->count() }}
        </span>
    </div>

    <div class="p-4 space-y-3">
        @if($items->count())
            {{ $slot }}
        @else
            <p class="text-sm text-slate-500">{{ $empty }}</p>
        @endif
    </div>
</div>