<div class="py-6 max-w-5xl mx-auto px-4">
    @if (! $booking)
        <div class="text-center py-16">
            <p class="text-slate-500">Mission introuvable.</p>
            <a href="{{ route('dashboard.client') }}" class="mt-4 inline-block text-indigo-600 hover:underline">← Retour</a>
        </div>
    @else
        <div class="flex justify-between items-center mb-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Suivi en direct</p>
                <h1 class="text-2xl font-black text-slate-900">Mission #{{ $booking->id }}</h1>
            </div>
            <a href="{{ route('dashboard.client') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold">← Retour</a>
        </div>

        <div class="rounded-2xl border bg-white shadow-sm overflow-hidden"
             x-data="trackingMapWidget({
                 bookingId: {{ $booking->id }},
                 dest: {
                     lat: {{ (float) ($session->destination_lat ?? $booking->destination_lat ?? 50.8503) }},
                     lng: {{ (float) ($session->destination_lng ?? $booking->destination_lng ?? 4.3517) }}
                 },
                 trackingUrl: '/api/client/bookings/{{ $booking->id }}/tracking',
                 trailUrl: '/api/client/bookings/{{ $booking->id }}/tracking/trail'
             })" x-init="boot()">
            <div id="tracking-map" style="height: 60vh; width: 100%; background: #e5e7eb;"></div>

            <div class="p-5 grid grid-cols-2 md:grid-cols-4 gap-3 border-t">
                <div>
                    <p class="text-xs uppercase font-bold text-slate-500">Statut</p>
                    <p class="text-lg font-bold" x-text="status || '—'"></p>
                </div>
                <div>
                    <p class="text-xs uppercase font-bold text-slate-500">ETA</p>
                    <p class="text-lg font-bold text-indigo-700">
                        <span x-text="etaMin !== null ? etaMin + ' min' : '—'"></span>
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase font-bold text-slate-500">Vitesse</p>
                    <p class="text-lg font-bold" x-text="speedKmh !== null ? speedKmh + ' km/h' : '—'"></p>
                </div>
                <div>
                    <p class="text-xs uppercase font-bold text-slate-500">Dernier ping</p>
                    <p class="text-lg font-bold" x-text="lastPing || '—'"></p>
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    window.trackingMapWidget = (cfg) => ({
        map: null,
        providerMarker: null,
        destMarker: null,
        trailLine: null,
        status: null,
        etaMin: null,
        speedKmh: null,
        lastPing: null,

        boot() {
            if (typeof L === 'undefined') {
                console.error('Leaflet not loaded');
                return;
            }
            const el = document.getElementById('tracking-map');
            if (!el) return;

            this.map = L.map('tracking-map').setView([cfg.dest.lat, cfg.dest.lng], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
                maxZoom: 19,
            }).addTo(this.map);

            this.destMarker = L.marker([cfg.dest.lat, cfg.dest.lng], {
                title: 'Votre adresse',
            }).addTo(this.map).bindPopup('🏠 Votre adresse').openPopup();

            this.fetchAll();
            setInterval(() => this.fetchTracking(), 8000);
            setInterval(() => this.fetchTrail(), 25000);
        },

        async fetchAll() {
            await this.fetchTrail();
            await this.fetchTracking();
        },

        async fetchTracking() {
            try {
                const resp = await fetch(cfg.trackingUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                if (!resp.ok) return;
                const json = await resp.json();
                const data = json.data;
                if (!data) return;
                this.status = data.status;
                this.etaMin = data.eta_minutes;
                this.lastPing = data.last_ping_at ? new Date(data.last_ping_at).toLocaleTimeString() : null;
                this.speedKmh = data.provider?.speed_mps !== null ? Math.round((data.provider.speed_mps || 0) * 3.6) : null;

                if (data.provider?.lat && data.provider?.lng) {
                    const pos = [data.provider.lat, data.provider.lng];
                    if (!this.providerMarker) {
                        this.providerMarker = L.marker(pos, {
                            title: 'Prestataire en route',
                        }).addTo(this.map).bindPopup('🚗 Prestataire');
                    } else {
                        this.providerMarker.setLatLng(pos);
                    }
                    this.map.fitBounds(L.latLngBounds([
                        [cfg.dest.lat, cfg.dest.lng],
                        pos,
                    ]).pad(0.3));
                }
            } catch (e) {
                console.error('tracking fetch fail', e);
            }
        },

        async fetchTrail() {
            try {
                const resp = await fetch(cfg.trailUrl + '?limit=50', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                if (!resp.ok) return;
                const json = await resp.json();
                const trail = (json.data || []).map(p => [p.lat, p.lng]);
                if (this.trailLine) {
                    this.map.removeLayer(this.trailLine);
                }
                if (trail.length >= 2) {
                    this.trailLine = L.polyline(trail, { color: '#6366f1', weight: 4, opacity: 0.7 }).addTo(this.map);
                }
            } catch (e) {}
        },
    });
</script>
@endpush
