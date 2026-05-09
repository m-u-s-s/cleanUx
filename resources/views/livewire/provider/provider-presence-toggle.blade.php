<div x-data="presenceToggle({{ $isOnline ? 'true' : 'false' }})"
     x-init="init($wire)"
     class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">

    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="relative flex h-3 w-3">
                @if ($isOnline)
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"></span>
                @else
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-slate-300"></span>
                @endif
            </span>
            <div>
                <h3 class="text-sm font-bold text-slate-900">
                    @if ($isOnline)
                        Vous êtes en ligne
                    @else
                        Vous êtes hors ligne
                    @endif
                </h3>
                <p class="text-xs text-slate-500">
                    @if ($isOnline && $wentOnlineAt)
                        Depuis {{ \Carbon\Carbon::parse($wentOnlineAt)->locale('fr')->diffForHumans() }}
                    @elseif ($isOnline)
                        Vous recevez des missions
                    @else
                        Activez pour recevoir des missions
                    @endif
                </p>
            </div>
        </div>

        @if ($isOnline)
            <button @click="goOffline()"
                    :disabled="loading"
                    class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 disabled:opacity-50">
                <span x-show="!loading">Passer hors ligne</span>
                <span x-show="loading">…</span>
            </button>
        @else
            <button @click="goOnline()"
                    :disabled="loading"
                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50">
                <span x-show="!loading">🟢 Passer en ligne</span>
                <span x-show="loading">Localisation…</span>
            </button>
        @endif
    </div>

    @if ($message)
        <div class="mt-3 flex items-start justify-between rounded-lg border px-3 py-2 text-xs
                {{ $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800' }}">
            <span>{{ $message }}</span>
            <button wire:click="clearMessage" class="ml-2 text-slate-500 hover:text-slate-700">✕</button>
        </div>
    @endif

    <div x-show="geoError" x-cloak class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
        <strong>Géolocalisation refusée.</strong> Active la permission GPS dans les paramètres du navigateur pour passer en ligne.
    </div>
</div>

@push('scripts')
<script>
    function presenceToggle(initialOnline) {
        return {
            isOnline: initialOnline,
            loading: false,
            geoError: false,
            heartbeatTimer: null,
            wire: null,

            init($wire) {
                this.wire = $wire;

                // Si déjà online au mount, démarrer le heartbeat
                if (this.isOnline) {
                    this.startHeartbeat();
                }

                // Listen confirmations Livewire
                Livewire.on('presence:online-confirmed', () => {
                    this.isOnline = true;
                    this.startHeartbeat();
                });

                Livewire.on('presence:offline-confirmed', () => {
                    this.isOnline = false;
                    this.stopHeartbeat();
                });

                // Stop heartbeat à la fermeture de l'onglet
                window.addEventListener('beforeunload', () => {
                    this.stopHeartbeat();
                });
            },

            async getCurrentPosition() {
                return new Promise((resolve, reject) => {
                    if (!navigator.geolocation) {
                        reject(new Error('Geolocation not supported'));
                        return;
                    }
                    navigator.geolocation.getCurrentPosition(
                        (pos) => resolve(pos),
                        (err) => reject(err),
                        { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
                    );
                });
            },

            async goOnline() {
                this.loading = true;
                this.geoError = false;
                try {
                    const pos = await this.getCurrentPosition();
                    const meta = {
                        accuracy_meters: pos.coords.accuracy,
                    };
                    if ('battery' in navigator) {
                        try {
                            const battery = await navigator.getBattery();
                            meta.battery_level = Math.round(battery.level * 100);
                        } catch (e) {}
                    }
                    await this.wire.goOnline(
                        pos.coords.latitude,
                        pos.coords.longitude,
                        meta
                    );
                } catch (err) {
                    this.geoError = true;
                    console.warn('[Presence] Geolocation failed:', err);
                } finally {
                    this.loading = false;
                }
            },

            async goOffline() {
                this.loading = true;
                this.stopHeartbeat();
                try {
                    await this.wire.goOffline();
                } finally {
                    this.loading = false;
                }
            },

            startHeartbeat() {
                this.stopHeartbeat();
                // Heartbeat toutes les 30 secondes
                this.heartbeatTimer = setInterval(() => this.sendHeartbeat(), 30 * 1000);
            },

            stopHeartbeat() {
                if (this.heartbeatTimer) {
                    clearInterval(this.heartbeatTimer);
                    this.heartbeatTimer = null;
                }
            },

            async sendHeartbeat() {
                if (!this.isOnline) return;
                try {
                    const pos = await this.getCurrentPosition();
                    const meta = {
                        accuracy_meters: pos.coords.accuracy,
                        speed_kmh: pos.coords.speed ? pos.coords.speed * 3.6 : null,
                        heading: pos.coords.heading,
                        app_state: document.hidden ? 'background' : 'foreground',
                    };
                    await this.wire.heartbeat(
                        pos.coords.latitude,
                        pos.coords.longitude,
                        meta
                    );
                } catch (err) {
                    console.warn('[Presence] Heartbeat failed:', err);
                }
            },
        };
    }
</script>
@endpush
