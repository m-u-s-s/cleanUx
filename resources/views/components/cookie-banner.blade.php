<div x-data="cookieBanner()" x-init="init()" x-show="visible" x-cloak
     class="fixed bottom-0 left-0 right-0 z-50 bg-slate-900 text-white shadow-2xl"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="translate-y-full"
     x-transition:enter-end="translate-y-0"
     style="display:none">
    <div class="max-w-7xl mx-auto p-4 md:p-6 grid grid-cols-1 lg:grid-cols-3 gap-4 items-center">
        <div class="lg:col-span-2">
            <p class="text-sm font-semibold mb-1">🍪 Nous utilisons des cookies</p>
            <p class="text-xs text-slate-300">
                Cookies essentiels actifs en permanence pour le fonctionnement du site. Les cookies analytics et marketing nécessitent votre consentement.
                <a href="{{ route('legal.cookies') }}" class="text-indigo-300 underline">En savoir plus</a>.
            </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2 lg:justify-end">
            <button x-on:click="acceptAll()" class="rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 text-sm font-bold">
                Tout accepter
            </button>
            <button x-on:click="rejectOptional()" class="rounded-lg bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 text-sm font-bold">
                Refuser optionnels
            </button>
            <button x-on:click="open = !open" class="rounded-lg border border-slate-600 hover:bg-slate-800 text-white px-4 py-2 text-sm font-semibold">
                Personnaliser
            </button>
        </div>

        <div x-show="open" x-cloak class="lg:col-span-3 mt-3 border-t border-slate-700 pt-3 space-y-2">
            <label class="flex items-center gap-3 text-sm">
                <input type="checkbox" checked disabled class="rounded">
                <span class="font-semibold">Essentiels</span>
                <span class="text-xs text-slate-400">(obligatoires — session, sécurité)</span>
            </label>
            <label class="flex items-center gap-3 text-sm">
                <input type="checkbox" x-model="prefs.analytics" class="rounded">
                <span class="font-semibold">Analytics</span>
                <span class="text-xs text-slate-400">(statistiques anonymes)</span>
            </label>
            <label class="flex items-center gap-3 text-sm">
                <input type="checkbox" x-model="prefs.marketing" class="rounded">
                <span class="font-semibold">Marketing</span>
                <span class="text-xs text-slate-400">(personnalisation publicité)</span>
            </label>
            <button x-on:click="savePrefs()" class="mt-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 text-sm font-bold">
                Enregistrer mes choix
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.cookieBanner = () => ({
        visible: false,
        open: false,
        prefs: { analytics: false, marketing: false },

        init() {
            const stored = this.read();
            if (! stored) {
                this.visible = true;
            }
            window.addEventListener('open-cookie-banner', () => {
                this.visible = true;
                this.open = true;
                const s = this.read();
                if (s) this.prefs = s;
            });
        },

        acceptAll() {
            this.prefs = { analytics: true, marketing: true };
            this.persist({ version: 1, ts: Date.now(), ...this.prefs });
            this.visible = false;
        },

        rejectOptional() {
            this.prefs = { analytics: false, marketing: false };
            this.persist({ version: 1, ts: Date.now(), ...this.prefs });
            this.visible = false;
        },

        savePrefs() {
            this.persist({ version: 1, ts: Date.now(), ...this.prefs });
            this.visible = false;
        },

        persist(data) {
            try {
                localStorage.setItem('cleanux_cookie_consent_v1', JSON.stringify(data));
                document.cookie = `cleanux_consent=${data.analytics?1:0}${data.marketing?1:0}; max-age=31536000; path=/; SameSite=Lax`;
            } catch (e) {}
        },

        read() {
            try {
                const raw = localStorage.getItem('cleanux_cookie_consent_v1');
                return raw ? JSON.parse(raw) : null;
            } catch (e) {
                return null;
            }
        },
    });
</script>
@endpush
