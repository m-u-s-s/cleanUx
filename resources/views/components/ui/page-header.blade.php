@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
])

<section {{ $attributes->merge(['class' => 'cu-page-header']) }}>
    <div class="min-w-0">
        @if($eyebrow)
            <span class="cu-eyebrow">{{ $eyebrow }}</span>
        @endif

        <h1 class="mt-3 text-2xl font-black tracking-tight text-slate-950 md:text-4xl">
            {{ $title }}
        </h1>

        @if($subtitle)
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 md:text-base">
                {{ $subtitle }}
            </p>
        @endif
    </div>

    @if(isset($actions))
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</section>
