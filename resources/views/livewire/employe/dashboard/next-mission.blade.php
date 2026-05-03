        @if($prochaineMission)
            <section class="overflow-hidden rounded-[2rem] bg-gradient-to-r from-blue-700 via-sky-700 to-indigo-700 p-6 text-white shadow-[0_18px_50px_rgba(37,99,235,0.22)]">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-blue-100">
                            Prochaine mission
                        </p>

                        <h2 class="mt-1 text-2xl font-black">
                            {{ $prochaineMission->service_display_name ?: 'Service non précisé' }}
                        </h2>

                        <p class="mt-2 text-sm text-blue-100">
                            📅 {{ $prochaineMission->date }} à {{ substr((string) $prochaineMission->heure, 0, 5) }}
                        </p>

                        <p class="mt-1 text-sm text-blue-100">
                            👤 {{ $prochaineMission->client->name ?? 'Client' }}
                            · 📍 {{ $prochaineMission->adresse ?? 'Adresse non précisée' }}, {{ $prochaineMission->ville ?? '—' }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if($prochaineMission->telephone_client)
                            <a href="tel:{{ $prochaineMission->telephone_client }}" class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-50">
                                📞 Appeler
                            </a>
                        @endif

                        @if($prochaineMission->adresse || $prochaineMission->ville)
                            <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($prochaineMission->adresse ?? '') . ' ' . ($prochaineMission->ville ?? '')) }}" target="_blank" class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-50">
                                📍 GPS
                            </a>
                        @endif

                        @if(Route::has('employe.missions'))
                            <a href="{{ route('employe.missions') }}" class="inline-flex items-center rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/20">
                                Voir workspace
                            </a>
                        @endif
                    </div>
                </div>
            </section>
        @endif
