<div
    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4"
    x-data="clientMissionLiveTracking({
        liveUrl: '{{ route('missions.tracking.live', $mission) }}'
    })">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Suivi live employé</h3>
            <p class="text-sm text-slate-500">Mission #{{ $mission->id }}</p>
        </div>

        <div class="text-right text-sm text-slate-500">
            <div class="flex items-center justify-end gap-2">
                <span class="inline-block h-3 w-3 rounded-full" :class="statusDotClass"></span>
                <span class="font-medium" :class="statusTextClass" x-text="statusLabel"></span>
            </div>
            <div>ETA : <span class="font-medium text-slate-800" x-text="etaLabel"></span></div>
            <div>Dernière mise à jour : <span class="font-medium text-slate-800" x-text="lastRefreshLabel"></span></div>
        </div>
    </div>

    <div class="flex justify-end">
        <button
            type="button"
            @click="fetchLive()"
            class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-800">
            Rafraîchir
        </button>
    </div>

    <div class="grid gap-3 md:grid-cols-4 text-sm">
        <div class="rounded-xl border border-slate-200 p-3">
            <p class="text-slate-500">Employé</p>
            <p class="font-semibold text-slate-900" x-text="employeeName ?? '—'"></p>
        </div>

        <div class="rounded-xl border border-slate-200 p-3">
            <p class="text-slate-500">Dernière position</p>
            <p class="font-semibold text-slate-900" x-text="lastPositionLabel"></p>
        </div>

        <div class="rounded-xl border border-slate-200 p-3">
            <p class="text-slate-500">Distance restante</p>
            <p class="font-semibold text-slate-900" x-text="distanceLabel"></p>
        </div>

        <div class="rounded-xl border border-slate-200 p-3">
            <p class="text-slate-500">Vitesse</p>
            <p class="font-semibold text-slate-900" x-text="speedLabel"></p>
        </div>
    </div>

    <div id="client-mission-live-map" class="h-80 w-full rounded-2xl border border-slate-200"></div>
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
            recordedAt: null,
            lastRefreshAt: null,

            get etaLabel() {
                return this.etaMinutes !== null ? `${this.etaMinutes} min` : '—';
            },

            get distanceLabel() {
                return this.distanceMeters !== null ? `${(this.distanceMeters / 1000).toFixed(1)} km` : '—';
            },

            get speedLabel() {
                return this.speedKmh !== null ? `${this.speedKmh.toFixed(1)} km/h` : '—';
            },

            get lastPositionLabel() {
                if (!this.lastLat || !this.lastLng) return '—';
                return `${Number(this.lastLat).toFixed(5)}, ${Number(this.lastLng).toFixed(5)}`;
            },

            get lastRefreshLabel() {
                return this.lastRefreshAt ? this.lastRefreshAt.toLocaleTimeString() : '—';
            },

            get statusLabel() {
                if (!this.status) return '—';

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

                return map[this.status] ?? this.status;
            },

            get statusDotClass() {
                const map = {
                    planned: 'bg-slate-400',
                    assigned: 'bg-indigo-500',
                    en_route: 'bg-blue-500',
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

                    if (!response.ok || !result.ok) return;

                    const data = result.data;

                    this.status = data.status;
                    this.employeeName = data.employee?.name ?? null;
                    this.etaMinutes = data.eta_minutes;
                    this.distanceMeters = data.distance_meters;
                    this.recordedAt = data.employee_position?.recorded_at ?? null;
                    this.speedKmh = data.employee_position?.speed_kmh ?? null;
                    this.lastLat = data.employee_position?.lat ?? null;
                    this.lastLng = data.employee_position?.lng ?? null;
                    this.destinationLat = data.destination?.lat ?? null;
                    this.destinationLng = data.destination?.lng ?? null;
                    this.lastRefreshAt = new Date();

                    this.syncMap();
                } catch (e) {
                    console.error(e);
                }
            },

            syncMap() {
                const mapEl = document.getElementById('client-mission-live-map');
                if (!mapEl) return;

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

                    this.employeeMarker.bindPopup('Employé');
                }

                if (this.destinationLat && this.destinationLng) {
                    const destinationLatLng = [this.destinationLat, this.destinationLng];

                    if (!this.destinationMarker) {
                        this.destinationMarker = L.marker(destinationLatLng).addTo(this.map);
                    } else {
                        this.destinationMarker.setLatLng(destinationLatLng);
                    }

                    this.destinationMarker.bindPopup('Destination');
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
                        padding: [30, 30]
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