@php
    $rdv = $mission->booking;
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
                {{ $rdv?->service_display_name ?: $mission->serviceCatalog?->name ?: 'Service à la demande' }}
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

                @if($rdv?->contact_phone)
                    <a href="tel:{{ $rdv->contact_phone }}" class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
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

        {{--
            TOUT VISIBLE D'UN COUP, EN CASES.

            Ce panneau etait une liste verticale de paires libelle/valeur : sur un telephone
            tenu d'une main sur un chantier, il fallait le parcourir ligne a ligne pour savoir
            a quelle heure commencer. En grille, l'oeil balaye au lieu de derouler.

            Les cases s'adaptent d'elles-memes : deux colonnes sur un ecran etroit, quatre sur
            une tablette, sans point de rupture ecrit a la main.
        --}}
        <div class="brio-terrain-panneau">
            <p class="brio-terrain-titre">Créneau mission</p>

            <div class="brio-terrain">
                <div class="brio-terrain-case brio-terrain-accent">
                    <span class="brio-terrain-tete">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" /><polyline points="12 7 12 12 15 14" />
                        </svg>
                        Début
                    </span>
                    <span class="brio-terrain-valeur">
                        {{ $mission->planned_start_at?->format('H:i') ?? '—' }}
                    </span>
                    <span class="brio-terrain-note">
                        {{ $mission->planned_start_at?->format('d/m') ?? ($rdv?->date?->format('d/m') ?? 'Non planifiée') }}
                    </span>
                </div>

                <div class="brio-terrain-case">
                    <span class="brio-terrain-tete">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" /><polyline points="12 7 12 12 16 10" />
                        </svg>
                        Fin prévue
                    </span>
                    <span class="brio-terrain-valeur">
                        {{ $mission->planned_end_at?->format('H:i') ?? '—' }}
                    </span>
                    @if($mission->planned_start_at && $mission->planned_end_at)
                        <span class="brio-terrain-note">
                            {{ $mission->planned_start_at->diffInMinutes($mission->planned_end_at) }} min
                        </span>
                    @endif
                </div>

                <div class="brio-terrain-case">
                    <span class="brio-terrain-tete">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" />
                        </svg>
                        Zone
                    </span>
                    <span class="brio-terrain-valeur brio-terrain-texte">
                        {{ $mission->serviceZone?->name ?? $rdv?->serviceZone?->name ?? '—' }}
                    </span>
                </div>

                <div class="brio-terrain-case">
                    <span class="brio-terrain-tete">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />
                        </svg>
                        Responsable
                    </span>
                    <span class="brio-terrain-valeur brio-terrain-texte">
                        {{ $mission->leadEmployee?->name ?? auth()->user()?->name ?? '—' }}
                    </span>
                </div>

                @if($rdv?->surface ?? null)
                    <div class="brio-terrain-case">
                        <span class="brio-terrain-tete">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                            </svg>
                            Surface
                        </span>
                        <span class="brio-terrain-valeur brio-terrain-texte">{{ $rdv->surface }}</span>
                    </div>
                @elseif($rdv?->surface_m2 ?? null)
                    <div class="brio-terrain-case">
                        <span class="brio-terrain-tete">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                            </svg>
                            Surface
                        </span>
                        <span class="brio-terrain-valeur">{{ $rdv->surface_m2 }}<span class="brio-terrain-unite">m²</span></span>
                    </div>
                @endif

                @if($rdv)
                    <div class="brio-terrain-case {{ $rdv->materiel_fournit ? 'brio-terrain-bon' : 'brio-terrain-attention' }}">
                        <span class="brio-terrain-tete">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
                            </svg>
                            Matériel
                        </span>
                        <span class="brio-terrain-valeur">{{ $rdv->materiel_fournit ? 'Fourni' : 'À apporter' }}</span>
                    </div>

                    <div class="brio-terrain-case {{ $rdv->presence_animaux ? 'brio-terrain-attention' : '' }}">
                        <span class="brio-terrain-tete">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                <circle cx="11" cy="4" r="2" /><circle cx="18" cy="8" r="2" /><circle cx="4" cy="8" r="2" />
                                <path d="M11 12c-3 0-5 2.5-5 5a3 3 0 0 0 3 3h4a3 3 0 0 0 3-3c0-2.5-2-5-5-5z" />
                            </svg>
                            Animaux
                        </span>
                        <span class="brio-terrain-valeur">{{ $rdv->presence_animaux ? 'Oui' : 'Non' }}</span>
                    </div>

                    <div class="brio-terrain-case {{ $rdv->acces_parking ? 'brio-terrain-bon' : 'brio-terrain-attention' }}">
                        <span class="brio-terrain-tete">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="3" /><path d="M9 17V7h4a3 3 0 0 1 0 6H9" />
                            </svg>
                            Parking
                        </span>
                        <span class="brio-terrain-valeur">{{ $rdv->acces_parking ? 'Oui' : 'Non' }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
