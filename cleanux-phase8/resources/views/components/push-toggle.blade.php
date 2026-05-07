{{--
  Phase 8 — Toggle d'activation des notifications push.
  
  À placer dans la page profil/paramètres :
    <x-push-toggle />
  
  Gère 4 états :
    - unsupported : navigateur ne supporte pas
    - denied      : user a bloqué (instructions pour ré-autoriser)
    - default     : pas encore demandé (bouton Activer)
    - granted+sub : actif (bouton Désactiver + bouton Test)
--}}

<div x-data="pushToggle()"
     x-init="init()"
     class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">

    <div class="flex items-start gap-3">
        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-100 text-xl">
            🔔
        </div>
        <div class="flex-1">
            <h3 class="text-sm font-bold text-slate-900">Notifications push</h3>
            <p class="mt-0.5 text-xs text-slate-600">
                Reçois des alertes même quand l'application n'est pas ouverte.
            </p>

            {{-- État: non supporté --}}
            <div x-show="status === 'unsupported'" x-cloak class="mt-2 rounded bg-slate-50 px-3 py-2 text-xs text-slate-600">
                ⓘ Ton navigateur ne supporte pas les notifications push. Essaie Chrome, Firefox ou Edge.
            </div>

            {{-- État: refusé --}}
            <div x-show="status === 'denied'" x-cloak class="mt-2 rounded bg-amber-50 px-3 py-2 text-xs text-amber-800">
                ⚠ Les notifications sont bloquées. Pour les réactiver :
                <ol class="ml-4 mt-1 list-decimal">
                    <li>Clique sur le cadenas 🔒 à gauche de l'URL</li>
                    <li>Active « Notifications »</li>
                    <li>Recharge la page</li>
                </ol>
            </div>

            {{-- État: pas encore demandé --}}
            <div x-show="status === 'default' && !subscribed" x-cloak class="mt-3">
                <button @click="enable()"
                        :disabled="loading"
                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                    <span x-show="!loading">Activer les notifications</span>
                    <span x-show="loading">Activation...</span>
                </button>
            </div>

            {{-- État: actif --}}
            <div x-show="status === 'granted' && subscribed" x-cloak class="mt-3">
                <div class="mb-2 flex items-center gap-1.5 text-xs text-emerald-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    <span class="font-medium">Notifications actives sur ce navigateur</span>
                </div>
                <div class="flex gap-2">
                    <button @click="test()"
                            :disabled="loading"
                            class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200 disabled:opacity-50">
                        Envoyer un test
                    </button>
                    <button @click="disable()"
                            :disabled="loading"
                            class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 disabled:opacity-50">
                        Désactiver
                    </button>
                </div>
            </div>

            {{-- État: granted mais pas subscribed (rare, après reset) --}}
            <div x-show="status === 'granted' && !subscribed" x-cloak class="mt-3">
                <button @click="enable()"
                        :disabled="loading"
                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                    Réactiver
                </button>
            </div>

            {{-- Flash message --}}
            <div x-show="message" x-cloak
                 class="mt-2 rounded px-2 py-1 text-xs"
                 :class="messageType === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'">
                <span x-text="message"></span>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function pushToggle() {
        return {
            status: 'unsupported',
            subscribed: false,
            loading: false,
            message: null,
            messageType: null,

            async init() {
                if (typeof window.cleanuxPush === 'undefined') {
                    this.status = 'unsupported';
                    return;
                }

                this.status = window.cleanuxPush.getStatus();

                if (this.status === 'granted') {
                    this.subscribed = await window.cleanuxPush.hasActiveSubscription();
                }
            },

            async enable() {
                this.loading = true;
                this.message = null;
                try {
                    await window.cleanuxPush.subscribe();
                    this.status = 'granted';
                    this.subscribed = true;
                    this.flash('✅ Notifications activées', 'success');
                } catch (err) {
                    console.error('[Push] enable failed', err);
                    this.status = window.cleanuxPush.getStatus();
                    this.flash('❌ ' + (err.message || 'Échec activation'), 'error');
                } finally {
                    this.loading = false;
                }
            },

            async disable() {
                this.loading = true;
                this.message = null;
                try {
                    await window.cleanuxPush.unsubscribe();
                    this.subscribed = false;
                    this.flash('Notifications désactivées', 'success');
                } catch (err) {
                    console.error('[Push] disable failed', err);
                    this.flash('❌ ' + (err.message || 'Échec désactivation'), 'error');
                } finally {
                    this.loading = false;
                }
            },

            async test() {
                this.loading = true;
                this.message = null;
                try {
                    await window.cleanuxPush.testNotification();
                    this.flash('📨 Test envoyé. Tu devrais recevoir une notif sous 1-2s.', 'success');
                } catch (err) {
                    this.flash('❌ Échec envoi test', 'error');
                } finally {
                    this.loading = false;
                }
            },

            flash(msg, type) {
                this.message = msg;
                this.messageType = type;
                setTimeout(() => { this.message = null; }, 5000);
            },
        };
    }
</script>
@endpush
