@if($prochaineMission)
    <section class="rounded-2xl border border-brand-200 bg-gradient-to-br from-brand-50 via-white to-white p-6 shadow-soft-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-brand-700">
                    <x-ui.icon name="rocket" class="w-3.5 h-3.5" />
                    Prochaine mission
                </p>

                <h2 class="mt-2 text-xl font-bold tracking-tight text-slate-900">
                    {{ $prochaineMission->service_display_name ?: 'Service non précisé' }}
                </h2>

                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-600">
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-slate-400" />
                        {{ $prochaineMission->date }} à {{ substr((string) $prochaineMission->heure, 0, 5) }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.icon name="user" class="w-3.5 h-3.5 text-slate-400" />
                        {{ $prochaineMission->client->name ?? 'Client' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 min-w-0">
                        <x-ui.icon name="map-pin" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span class="truncate">{{ $prochaineMission->adresse ?? 'Adresse non précisée' }}, {{ $prochaineMission->ville ?? '—' }}</span>
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 shrink-0">
                @if($prochaineMission->contact_phone)
                    <a href="tel:{{ $prochaineMission->contact_phone }}" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition">
                        <x-ui.icon name="phone" class="w-4 h-4" />
                        Appeler
                    </a>
                @endif

                @if($prochaineMission->adresse || $prochaineMission->ville)
                    <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($prochaineMission->adresse ?? '') . ' ' . ($prochaineMission->ville ?? '')) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                        <x-ui.icon name="map-pin" class="w-4 h-4" />
                        GPS
                    </a>
                @endif

                @if(Route::has('employe.missions'))
                    <a href="{{ route('employe.missions') }}" class="brio-btn-primary inline-flex items-center gap-1.5">
                        <span>Workspace</span>
                        <x-ui.icon name="arrow-right" class="w-4 h-4" />
                    </a>
                @endif
            </div>
        </div>
    </section>
@endif
