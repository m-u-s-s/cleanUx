@props([
    'title',
    'value',
    'subtitle' => null,
])

<div class="rounded-2xl border bg-white p-5 shadow-sm">
    <p class="text-sm text-slate-500">{{ $title }}</p>
    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $value }}</p>

    @if($subtitle)
        <p class="mt-1 text-xs text-slate-500">{{ $subtitle }}</p>
    @endif
</div>