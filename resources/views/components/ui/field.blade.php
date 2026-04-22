@props([
    'label' => null,
    'hint' => null,
    'error' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    @if($label)
        <label class="text-sm font-semibold text-slate-700">{{ $label }}</label>
    @endif

    {{ $slot }}

    @if($hint)
        <p class="text-xs leading-5 text-slate-500">{{ $hint }}</p>
    @endif

    @if($error)
        <p class="text-xs font-medium text-red-600">{{ $error }}</p>
    @endif
</div>
