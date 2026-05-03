@props([
    'href' => null,
    'title',
    'description' => null,
    'icon' => '↗️',
    'tone' => 'sky',
])

@php
    $toneClasses = match($tone) {
        'emerald' => 'border-emerald-100 bg-emerald-50/80 text-emerald-800 hover:border-emerald-200 hover:bg-emerald-50',
        'amber' => 'border-amber-100 bg-amber-50/80 text-amber-800 hover:border-amber-200 hover:bg-amber-50',
        'rose' => 'border-rose-100 bg-rose-50/80 text-rose-800 hover:border-rose-200 hover:bg-rose-50',
        'slate' => 'border-slate-200 bg-slate-50/80 text-slate-800 hover:border-slate-300 hover:bg-white',
        default => 'border-sky-100 bg-sky-50/80 text-sky-800 hover:border-sky-200 hover:bg-sky-50',
    };
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'cu-action-card '.$toneClasses]) }}>
        <span class="cu-action-card-icon">{{ $icon }}</span>
        <span class="min-w-0">
            <span class="block font-black text-slate-900">{{ $title }}</span>
            @if($description)
                <span class="mt-1 block text-sm leading-5 text-slate-500">{{ $description }}</span>
            @endif
        </span>
    </a>
@else
    <div {{ $attributes->merge(['class' => 'cu-action-card '.$toneClasses]) }}>
        <span class="cu-action-card-icon">{{ $icon }}</span>
        <span class="min-w-0">
            <span class="block font-black text-slate-900">{{ $title }}</span>
            @if($description)
                <span class="mt-1 block text-sm leading-5 text-slate-500">{{ $description }}</span>
            @endif
        </span>
    </div>
@endif
