@props([
    'title',
    'subtitle' => null,
    'badge' => null,
])

<div class="flex items-start justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3">
    <div>
        <p class="text-sm font-medium text-slate-900">
            {{ $title }}
        </p>

        @if($subtitle)
            <p class="mt-1 text-xs text-slate-500">
                {{ $subtitle }}
            </p>
        @endif
    </div>

    @if($badge)
        <span class="shrink-0 rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 border">
            {{ $badge }}
        </span>
    @endif
</div>