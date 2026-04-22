<div
    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-4"
    x-data="missionRouteTracking({
        missionId: {{ $mission->id }},
        startUrl: '{{ route('missions.tracking.start', $mission) }}',
        arrivedUrl: '{{ route('missions.arrived', $mission) }}',
        liveSessionId: {{ $mission->activeTrackingSession?->id ?? 'null' }},
        stopUrlTemplate: '{{ url('/mission-tracking-sessions/__ID__/tracking/stop') }}',
        pushUrlTemplate: '{{ url('/mission-tracking-sessions/__ID__/tracking/push') }}',
        csrf: '{{ csrf_token() }}',
        destinationLat: {{ $mission->destination_lat ?? 'null' }},
        destinationLng: {{ $mission->destination_lng ?? 'null' }},
        geofenceMeters: 120
    })"
    x-on:mission-en-route-start-tracking.window="
        if ($event.detail.missionId === {{ $mission->id }}) {
            startTracking()
        }
    "
>
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Trajet mission</h3>
            <p class="text-sm text-slate-500">Statut mission : <span class="font-medium text-slate-800">{{ $mission->status }}</span></p>
        </div>

        <div class="text-sm text-slate-500">
            Session active :
            <span class="font-medium text-slate-800" x-text="tracking ? 'Oui' : 'Non'"></span>
        </div>
    </div>

    <template x-if="message">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="message"></div>
    </template>

    <template x-if="error">
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="error"></div>
    </template>

    <template x-if="nearDestination && tracking">
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
            Vous êtes proche de la destination. Vous pouvez confirmer l’arrivée.
        </div>
    </template>

    <div class="grid gap-3 md:grid-cols-3">
        <button
            type="button"
            @click="startTracking()"
            class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
            :disabled="tracking"
        >
            Démarrer trajet
        </button>

        <button
            type="button"
            @click="confirmArrived()"
            class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
            :disabled="!nearDestination"
        >
            Confirmer Arrivé
        </button>

        <button
            type="button"
            @click="stopTracking()"
            class="rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
            :disabled="!tracking"
        >
            Arrêter trajet
        </button>
    </div>

    <div class="grid gap-3 md:grid-cols-4 text-sm">
        <div class="rounded-xl border border-slate-200 p-3">
            <p class="text-slate-500">Tracking</p>
            <p class="font-semibold text-slate-900" x-text="tracking ? 'Actif' : 'Inactif'"></p>
        </div>

        <div class="rounded-xl border border-slate-200 p-3">
            <p class="text-slate-500">Session</p>
            <p class="font-semibold text-slate-900" x-text="sessionId ?? '—'"></p>
        </div>

        <div class="rounded-xl border border-slate-200 p-3">
            <p class="text-slate-500">Dernier envoi</p>
            <p class="font-semibold text-slate-900" x-text="lastPushAt ?? '—'"></p>
        </div>

        <div class="rounded-xl border border-slate-200 p-3">
            <p class="text-slate-500">Distance restante</p>
            <p class="font-semibold text-slate-900" x-text="distanceLabel"></p>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function missionRouteTracking(config) {
                return {
                    tracking: !!config.liveSessionId,
                    sessionId: config.liveSessionId,
                    watchId: null,
                    message: null,
                    error: null,
                    lastPushAt: null,
                    startUrl: config.startUrl,
                    arrivedUrl: config.arrivedUrl,
                    stopUrlTemplate: config.stopUrlTemplate,
                    pushUrlTemplate: config.pushUrlTemplate,
                    csrf: config.csrf,
                    destinationLat: config.destinationLat,
                    destinationLng: config.destinationLng,
                    geofenceMeters: config.geofenceMeters ?? 120,
                    currentLat: null,
                    currentLng: null,
                    nearDestination: false,
                    distanceMeters: null,

                    get distanceLabel() {
                        return this.distanceMeters !== null ? `${(this.distanceMeters / 1000).toFixed(2)} km` : '—';
                    },

                    async startTracking() {
                        this.message = null;
                        this.error = null;

                        if (!navigator.geolocation) {
                            this.error = 'La géolocalisation n’est pas disponible.';
                            return;
                        }

                        navigator.geolocation.getCurrentPosition(async (position) => {
                            try {
                                const response = await fetch(this.startUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': this.csrf,
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        lat: position.coords.latitude,
                                        lng: position.coords.longitude,
                                    }),
                                });

                                const result = await response.json();

                                if (!response.ok || !result.ok) {
                                    throw new Error(result.message || 'Impossible de démarrer le trajet.');
                                }

                                this.currentLat = position.coords.latitude;
                                this.currentLng = position.coords.longitude;
                                this.sessionId = result.session_id;
                                this.tracking = true;
                                this.computeGeofence();
                                this.message = 'Trajet démarré.';
                                this.startWatcher();
                            } catch (e) {
                                this.error = e.message;
                            }
                        }, () => {
                            this.error = 'Impossible de récupérer la position actuelle.';
                        }, {
                            enableHighAccuracy: true,
                            timeout: 10000,
                            maximumAge: 0,
                        });
                    },

                    startWatcher() {
                        if (!this.sessionId) return;

                        if (this.watchId !== null) {
                            navigator.geolocation.clearWatch(this.watchId);
                        }

                        this.watchId = navigator.geolocation.watchPosition(async (position) => {
                            this.currentLat = position.coords.latitude;
                            this.currentLng = position.coords.longitude;
                            this.computeGeofence();

                            try {
                                await fetch(this.pushUrlTemplate.replace('__ID__', this.sessionId), {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': this.csrf,
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        lat: position.coords.latitude,
                                        lng: position.coords.longitude,
                                        accuracy_meters: position.coords.accuracy,
                                        speed_kmh: position.coords.speed ? position.coords.speed * 3.6 : null,
                                        heading: position.coords.heading,
                                        source: 'browser',
                                        app_state: 'foreground',
                                    }),
                                });

                                this.lastPushAt = new Date().toLocaleTimeString();
                            } catch (e) {
                                console.error(e);
                            }
                        }, (error) => {
                            console.error(error);
                        }, {
                            enableHighAccuracy: true,
                            timeout: 10000,
                            maximumAge: 5000,
                        });
                    },

                    async confirmArrived() {
                        this.message = null;
                        this.error = null;

                        try {
                            const response = await fetch(this.arrivedUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    lat: this.currentLat,
                                    lng: this.currentLng,
                                }),
                            });

                            const result = await response.json();

                            if (!response.ok || !result.ok) {
                                throw new Error(result.message || 'Impossible de confirmer l’arrivée.');
                            }

                            await this.stopTrackingSilently();
                            this.message = 'Arrivée confirmée.';
                        } catch (e) {
                            this.error = e.message;
                        }
                    },

                    async stopTracking() {
                        this.message = null;
                        this.error = null;

                        try {
                            await this.stopTrackingSilently();
                            this.message = 'Trajet arrêté.';
                        } catch (e) {
                            this.error = e.message;
                        }
                    },

                    async stopTrackingSilently() {
                        if (!this.sessionId) {
                            this.tracking = false;
                            return;
                        }

                        const response = await fetch(this.stopUrlTemplate.replace('__ID__', this.sessionId), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                lat: this.currentLat,
                                lng: this.currentLng,
                            }),
                        });

                        const result = await response.json();

                        if (!response.ok || !result.ok) {
                            throw new Error(result.message || 'Impossible d’arrêter le trajet.');
                        }

                        if (this.watchId !== null) {
                            navigator.geolocation.clearWatch(this.watchId);
                            this.watchId = null;
                        }

                        this.tracking = false;
                        this.sessionId = null;
                        this.nearDestination = false;
                    },

                    computeGeofence() {
                        if (
                            this.currentLat === null || this.currentLng === null ||
                            this.destinationLat === null || this.destinationLng === null
                        ) {
                            this.nearDestination = false;
                            this.distanceMeters = null;
                            return;
                        }

                        const distance = this.distanceMetersBetween(
                            this.currentLat,
                            this.currentLng,
                            this.destinationLat,
                            this.destinationLng
                        );

                        this.distanceMeters = Math.round(distance);
                        this.nearDestination = distance <= this.geofenceMeters;
                    },

                    distanceMetersBetween(lat1, lng1, lat2, lng2) {
                        const earthRadius = 6371000;
                        const dLat = this.toRad(lat2 - lat1);
                        const dLng = this.toRad(lng2 - lng1);

                        const a =
                            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                            Math.cos(this.toRad(lat1)) * Math.cos(this.toRad(lat2)) *
                            Math.sin(dLng / 2) * Math.sin(dLng / 2);

                        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

                        return earthRadius * c;
                    },

                    toRad(value) {
                        return value * Math.PI / 180;
                    },

                    init() {
                        if (this.tracking && this.sessionId) {
                            this.startWatcher();
                        }
                    }
                }
            }
        </script>
    @endpush
@endonce