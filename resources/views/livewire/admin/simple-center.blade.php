<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="cu-hero">
            <div class="relative">
                <span class="cu-eyebrow">{{ $eyebrow ?? 'Administration' }}</span>

                <h1 class="mt-3 text-3xl font-black text-slate-900">
                    {{ $title ?? 'Centre admin' }}
                </h1>

                <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach($sections ?? [] as $section)
                        <div class="rounded-2xl border bg-white p-5 shadow-sm">
                            <p class="font-bold text-slate-900">{{ $section }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>