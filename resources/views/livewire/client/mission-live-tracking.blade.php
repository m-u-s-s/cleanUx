<div
    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-5"
    x-data="clientMissionLiveTracking({
        liveUrl: '{{ route('missions.tracking.live', $mission) }}'
    })">

    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">🚗 Employé en route</h3>
            <p class="text-sm text-slate-500">
                Suivi en temps réel de votre intervention.
            </p>
        </div>

        <div class="rounded-2xl bg-blue-50 border border-blue-200 px-5 py-4 text-center min-w-[180px]">
            <p class="text-xs uppercase tracking-wide text-blue-600 font-semibold">Arrivée estimée</p>
            <p class="mt-1 text-2xl font-bold text-blue-800" x-text="arrivalSentence"></p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
        <div class="rounded-xl border border-slate-200 p-4 bg-slate-50">
            <p class="text-slate-500">Statut</p>
            <div class="mt-1 flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-full" :class="statusDotClass"></span>
                <span class="font-semibold" :class="statusTextClass" x-text="statusLabel"></span>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 p-4 bg-slate-50">
            <p class="text-slate-500">Employé</p>
            <p class="mt-1 font-semibold text-slate-900" x-text="employeeName ?? 'Employé à confirmer'"></p>
        </div>

        <div class="rounded-xl border border-slate-200 p-4 bg-slate-50">
            <p class="text-slate-500">Distance restante</p>
            <p class="mt-1 font-semibold text-slate-900" x-text="distanceLabel"></p>
        </div>

        <div class="rounded-xl border border-slate-200 p-4 bg-slate-50">
            <p class="text-slate-500">Dernière mise à jour</p>
            <p class="mt-1 font-semibold text-slate-900" x-text="lastRefreshLabel"></p>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <p class="font-semibold text-slate-900">Carte simplifiée</p>
                <p class="text-sm text-slate-500">
                    La position affichée dépend du GPS de l’employé.
                </p>
            </div>

            <button
                type="button"
                @click="fetchLive()"
                class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                🔄 Rafraîchir
            </button>
        </div>

        <div id="client-mission-live-map" class="h-80 w-full rounded-2xl border border-slate-300 overflow-hidden"></div>
    </div>

    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        ℹ️ Quand l’employé arrive, un code de début sera affiché pour lancer officiellement la mission.
    </div>
</div>

@once
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    function clientMissionLiveTracking(config) {
        return {
            liveUrl: config.liveUrl,

            map: null,
            employeeMarker: null,
            destinationMarker: null,
            line: null,
            intervalId: null,

            status: null,
            employeeName: null,
            etaMinutes: null,
            distanceMeters: null,
            speedKmh: null,
            lastLat: null,
            lastLng: null,
            destinationLat: null,
            destinationLng: null,
            lastRefreshAt: null,

            get arrivalSentence() {
                if (this.etaMinutes === null) {
                    return '—';
                }

                if (this.etaMinutes <= 1) {
                    return 'Arrive maintenant';
                }

                return `Arrive dans ${this.etaMinutes} min`;
            },

            get distanceLabel() {
                if (this.distanceMeters === null) {
                    return '—';
                }

                if (this.distanceMeters < 1000) {
                    return `${this.distanceMeters} m`;
                }

                return `${(this.distanceMeters / 1000).toFixed(1)} km`;
            },

            get lastRefreshLabel() {
                return this.lastRefreshAt
                    ? this.lastRefreshAt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                    : '—';
            },

            get statusLabel() {
                const map = {
                    planned: 'Planifiée',
                    assigned: 'Assignée',
                    en_route: 'En route',
                    arrived: 'Arrivé',
                    started: 'En cours',
                    paused: 'En pause',
                    completed: 'Terminée',
                    cancelled: 'Annulée',
                };

                return map[this.status] ?? 'En attente';
            },

            get statusDotClass() {
                const map = {
                    planned: 'bg-slate-400',
                    assigned: 'bg-indigo-500',
                    en_route: 'bg-blue-500 animate-pulse',
                    arrived: 'bg-amber-500',
                    started: 'bg-emerald-500',
                    paused: 'bg-orange-500',
                    completed: 'bg-green-600',
                    cancelled: 'bg-red-500',
                };

                return map[this.status] ?? 'bg-slate-400';
            },

            get statusTextClass() {
                const map = {
                    planned: 'text-slate-700',
                    assigned: 'text-indigo-700',
                    en_route: 'text-blue-700',
                    arrived: 'text-amber-700',
                    started: 'text-emerald-700',
                    paused: 'text-orange-700',
                    completed: 'text-green-700',
                    cancelled: 'text-red-700',
                };

                return map[this.status] ?? 'text-slate-700';
            },

            async fetchLive() {
                try {
                    const response = await fetch(this.liveUrl, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    const result = await response.json();

                    if (!response.ok || !result.ok) {
                        return;
                    }

                    const data = result.data;

                    this.status = data.status;
                    this.employeeName = data.employee?.name ?? null;
                    this.etaMinutes = data.eta_minutes;
                    this.distanceMeters = data.distance_meters;
                    this.speedKmh = data.employee_position?.speed_kmh ?? null;
                    this.lastLat = data.employee_position?.lat ?? null;
                    this.lastLng = data.employee_position?.lng ?? null;
                    this.destinationLat = data.destination?.lat ?? null;
                    this.destinationLng = data.destination?.lng ?? null;
                    this.lastRefreshAt = new Date();

                    this.syncMap();
                } catch (error) {
                    console.error(error);
                }
            },

            syncMap() {
                const mapEl = document.getElementById('client-mission-live-map');

                if (!mapEl) {
                    return;
                }

                if (!this.map) {
                    this.map = L.map(mapEl).setView([50.8503, 4.3517], 12);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap',
                    }).addTo(this.map);
                }

                if (this.lastLat && this.lastLng) {
                    const employeeLatLng = [this.lastLat, this.lastLng];

                    if (!this.employeeMarker) {
                        this.employeeMarker = L.marker(employeeLatLng).addTo(this.map);
                    } else {
                        this.employeeMarker.setLatLng(employeeLatLng);
                    }

                    this.employeeMarker.bindPopup('Employé en route');
                }

                if (this.destinationLat && this.destinationLng) {
                    const destinationLatLng = [this.destinationLat, this.destinationLng];

                    if (!this.destinationMarker) {
                        this.destinationMarker = L.marker(destinationLatLng).addTo(this.map);
                    } else {
                        this.destinationMarker.setLatLng(destinationLatLng);
                    }

                    this.destinationMarker.bindPopup('Votre adresse');
                }

                if (this.lastLat && this.lastLng && this.destinationLat && this.destinationLng) {
                    const points = [
                        [this.lastLat, this.lastLng],
                        [this.destinationLat, this.destinationLng]
                    ];

                    if (!this.line) {
                        this.line = L.polyline(points).addTo(this.map);
                    } else {
                        this.line.setLatLngs(points);
                    }

                    this.map.fitBounds(this.line.getBounds(), {
                        padding: [40, 40]
                    });
                } else if (this.lastLat && this.lastLng) {
                    this.map.setView([this.lastLat, this.lastLng], 14);
                }

                setTimeout(() => {
                    this.map.invalidateSize();
                }, 150);
            },

            init() {
                this.fetchLive();
                this.intervalId = setInterval(() => this.fetchLive(), 8000);
            }
        }
    }
</script>
@endpush
@endonce