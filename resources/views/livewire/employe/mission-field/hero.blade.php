@php
    $rdv = $mission->rendezVous;
    $client = $rdv?->client;
    $statusLabels = [
        'planned' => 'Planifiée',
        'assigned' => 'Assignée',
        'en_route' => 'En route',
        'arrived' => 'Sur place',
        'started' => 'Démarrée',
        'paused' => 'En pause',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
    ];

    $statusClasses = [
        'planned' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'assigned' => 'bg-blue-50 text-blue-700 ring-blue-100',
        'en_route' => 'bg-indigo-50 text-indigo-700 ring-indigo-100',
        'arrived' => 'bg-sky-50 text-sky-700 ring-sky-100',
        'started' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        'paused' => 'bg-amber-50 text-amber-700 ring-amber-100',
        'completed' => 'bg-green-50 text-green-700 ring-green-100',
        'cancelled' => 'bg-rose-50 text-rose-700 ring-rose-100',
    ];
@endphp

<section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 text-white shadow-sm">
    <div class="grid gap-6 px-5 py-6 lg:grid-cols-[minmax(0,1.25fr)_minmax(300px,0.75fr)] lg:px-8 lg:py-8">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-black uppercase tracking-[0.2em] text-sky-200 ring-1 ring-white/10">
                    Terrain
                </span>
                <span class="rounded-full px-3 py-1 text-xs font-black ring-1 {{ $statusClasses[$mission->status] ?? 'bg-white/10 text-white ring-white/20' }}">
                    {{ $statusLabels[$mission->status] ?? ucfirst((string) $mission->status) }}
                </span>
            </div>

            <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">
                Mission #{{ $mission->id }}
            </h1>

            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200 sm:text-base">
                {{ $rdv?->service_display_name ?: $mission->serviceCatalog?->name ?: 'Service de nettoyage' }}
                @if($client?->name)
                    <span class="text-slate-400">·</span> {{ $client->name }}
                @endif
            </p>

            <div class="mt-5 flex flex-wrap gap-3">
                @if(Route::has('employe.missions'))
                    <a href="{{ route('employe.missions') }}" class="rounded-2xl bg-white px-4 py-2 text-sm font-black text-slate-900 transition hover:bg-slate-100">
                        ← Mes missions
                    </a>
                @endif

                @if($rdv?->telephone_client)
                    <a href="tel:{{ $rdv->telephone_client }}" class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                        📞 Appeler client
                    </a>
                @endif

                @if($rdv?->adresse || $rdv?->ville)
                    <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($rdv->adresse ?? '').' '.($rdv->ville ?? '')) }}"
                       target="_blank"
                       class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                        📍 Ouvrir GPS
                    </a>
                @endif
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">Créneau mission</p>

            <div class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-300">Début prévu</span>
                    <span class="font-black text-white">
                        {{ $mission->planned_start_at?->format('d/m/Y H:i') ?? ($rdv?->date?->format('d/m/Y') ?? '—') }}
                    </span>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-300">Fin prévue</span>
                    <span class="font-black text-white">
                        {{ $mission->planned_end_at?->format('H:i') ?? '—' }}
                    </span>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-300">Zone</span>
                    <span class="font-black text-white">
                        {{ $mission->serviceZone?->name ?? $rdv?->serviceZone?->name ?? '—' }}
                    </span>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-300">Responsable</span>
                    <span class="font-black text-white">
                        {{ $mission->leadEmployee?->name ?? auth()->user()?->name ?? '—' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>
