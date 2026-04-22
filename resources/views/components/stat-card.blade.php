@props([
    'title',
    'value',
    'hint' => null,
])

<div class="bg-white rounded-2xl shadow-sm border p-4">
    <p class="text-sm text-gray-500">{{ $title }}</p>
    <p class="text-2xl font-bold text-slate-800 mt-1">{{ $value }}</p>

    @if($hint)
        <p class="text-xs text-gray-400 mt-2">{{ $hint }}</p>
    @endif
</div>