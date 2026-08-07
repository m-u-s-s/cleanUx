{{-- KPI skeleton cards — matches the 4-column grid in kpis.blade.php --}}
<div wire:loading.block class="space-y-6">

    {{-- KPI row --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @for($i = 0; $i < 4; $i++)
        <div class="brio-card animate-pulse">
            <div class="flex items-center gap-3">
                <div class="h-11 w-11 rounded-xl bg-surface-200"></div>
                <div class="flex-1 space-y-2">
                    <div class="h-3 w-20 rounded bg-surface-200"></div>
                    <div class="h-6 w-16 rounded bg-surface-200"></div>
                </div>
            </div>
        </div>
        @endfor
    </div>

    {{-- Main content skeleton --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        {{-- Left column --}}
        <div class="space-y-6 xl:col-span-2">
            <div class="brio-card animate-pulse p-6">
                <div class="mb-4 flex items-center gap-3">
                    <div class="h-5 w-36 rounded bg-surface-200"></div>
                    <div class="h-4 w-20 rounded bg-surface-200"></div>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 space-y-3">
                        <div class="h-5 w-1/2 rounded bg-surface-200"></div>
                        <div class="h-4 w-1/3 rounded bg-surface-200"></div>
                        <div class="h-4 w-2/5 rounded bg-surface-200"></div>
                    </div>
                    <div class="h-16 w-28 rounded-xl bg-surface-200"></div>
                </div>
                <div class="mt-6 grid grid-cols-3 gap-3">
                    @for($j = 0; $j < 3; $j++)
                    <div class="h-16 rounded-xl bg-surface-200"></div>
                    @endfor
                </div>
                <div class="mt-6 flex gap-2">
                    <div class="h-9 w-28 rounded-lg bg-surface-200"></div>
                    <div class="h-9 w-24 rounded-lg bg-surface-200"></div>
                </div>
            </div>

            {{-- Intervention list skeleton --}}
            <div class="brio-card animate-pulse p-6 space-y-3">
                <div class="h-5 w-40 rounded bg-surface-200 mb-4"></div>
                @for($k = 0; $k < 3; $k++)
                <div class="rounded-xl border border-surface-100 p-4 space-y-2">
                    <div class="h-4 w-1/2 rounded bg-surface-200"></div>
                    <div class="h-3 w-1/3 rounded bg-surface-200"></div>
                </div>
                @endfor
            </div>
        </div>

        {{-- Right column --}}
        <div class="space-y-6">
            <div class="brio-card animate-pulse p-6 space-y-3">
                <div class="h-4 w-28 rounded bg-surface-200"></div>
                @for($l = 0; $l < 3; $l++)
                <div class="h-10 rounded-lg bg-surface-200"></div>
                @endfor
            </div>

            <div class="brio-card animate-pulse p-6 space-y-3">
                <div class="h-4 w-24 rounded bg-surface-200"></div>
                <div class="grid grid-cols-2 gap-2.5">
                    @for($m = 0; $m < 4; $m++)
                    <div class="h-16 rounded-lg bg-surface-200"></div>
                    @endfor
                </div>
            </div>
        </div>
    </div>
</div>
