@props([
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'actions' => null,
])

<div class="cu-hero">
    <div class="relative cu-toolbar gap-4">
        <div class="max-w-3xl">
            @if($eyebrow)
                <span class="cu-eyebrow">{{ $eyebrow }}</span>
            @endif

            <h2 class="mt-3 text-2xl font-black tracking-tight text-slate-900 md:text-3xl">
                {{ $title }}
            </h2>

            @if($subtitle)
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 md:text-base">
                    {{ $subtitle }}
                </p>
            @endif
        </div>

        @if($actions)
            <div class="relative flex flex-wrap gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>

    @if(trim((string) $slot))
        <div class="relative mt-6">
            {{ $slot }}
        </div>
    @endif
</div>
