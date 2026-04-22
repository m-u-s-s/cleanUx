@props([
    'message' => 'Chargement en cours…',
])

<div {{ $attributes->merge(['class' => 'cu-card p-5']) }}>
    <div class="flex items-start gap-4">
        <div class="cu-skeleton h-12 w-12 rounded-2xl"></div>
        <div class="flex-1 space-y-3">
            <div class="cu-skeleton h-4 w-40"></div>
            <div class="cu-skeleton h-3 w-full"></div>
            <div class="cu-skeleton h-3 w-5/6"></div>
        </div>
    </div>
    <p class="mt-4 text-sm text-slate-500">{{ $message }}</p>
</div>
