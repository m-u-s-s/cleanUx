<div class="mx-auto max-w-5xl px-4 py-6">
    <div class="mb-6">
        <a href="{{ route('client-company.bookings.index') }}" class="text-xs text-slate-500 hover:text-slate-700">← Réservations</a>
        <h1 class="mt-1 text-2xl font-bold text-slate-900">Photos de l'intervention</h1>
        <p class="text-sm text-slate-600">
            Réservation {{ $mission->booking?->booking_reference ?? '—' }}
            @if ($mission->leadEmployee) · {{ $mission->leadEmployee->name }} @endif
        </p>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <h2 class="mb-2 text-sm font-semibold text-slate-700">Avant ({{ count($beforePhotos) }})</h2>
            @if (count($beforePhotos) === 0)
                <p class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500">Aucune photo avant.</p>
            @else
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($beforePhotos as $photo)
                        <a href="{{ \App\Support\Media\PrivateMedia::url($photo->path) }}" target="_blank" rel="noopener"
                            class="block overflow-hidden rounded-xl border border-slate-200">
                            <img src="{{ \App\Support\Media\PrivateMedia::url($photo->path) }}"
                                alt="Photo avant" class="h-32 w-full object-cover" loading="lazy">
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h2 class="mb-2 text-sm font-semibold text-slate-700">Après ({{ count($afterPhotos) }})</h2>
            @if (count($afterPhotos) === 0)
                <p class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500">Aucune photo après.</p>
            @else
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($afterPhotos as $photo)
                        <a href="{{ \App\Support\Media\PrivateMedia::url($photo->path) }}" target="_blank" rel="noopener"
                            class="block overflow-hidden rounded-xl border border-slate-200">
                            <img src="{{ \App\Support\Media\PrivateMedia::url($photo->path) }}"
                                alt="Photo après" class="h-32 w-full object-cover" loading="lazy">
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
