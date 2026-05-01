<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="cu-hero">
            <div class="relative">
                <span class="cu-eyebrow">Administration</span>

                <h1 class="mt-3 text-3xl font-black text-slate-900">
                    {{ $title ?? 'Module admin' }}
                </h1>

                <p class="mt-2 text-sm text-slate-500">
                    {{ $subtitle ?? 'Centre administratif CleanUx.' }}
                </p>
            </div>
        </div>
    </div>
</x-app-layout>