{{--
    LE CONSENTEMENT, EN VERRE.

    L'ancien bandeau barrait toute la largeur en aplat ardoise et mangeait la moitie
    de l'ecran mobile. Il devient un panneau flottant, compact, qui laisse voir la
    page derriere lui — un consentement ne doit pas prendre l'ecran en otage.

    Le contrat Alpine ne change pas : `visible`, `open`, `prefs`, `acceptAll()`,
    `rejectOptional()`, `savePrefs()`, `init()`.
--}}
<div x-data="cookieBanner()" x-init="init()" x-show="visible" x-cloak
     class="brio-cookies"
     x-transition:enter="transition ease-out duration-500"
     x-transition:enter-start="opacity-0 translate-y-6"
     x-transition:enter-end="opacity-100 translate-y-0"
     role="dialog"
     aria-live="polite"
     aria-label="Consentement aux cookies"
     style="display:none">
    <div class="brio-cookies-corps">
        <div class="brio-cookies-texte">
            <p class="brio-cookies-titre">Nous utilisons des cookies</p>
            <p class="brio-cookies-detail">
                Les cookies essentiels restent actifs pour faire fonctionner le site. Les mesures
                d'audience et la personnalisation attendent votre accord.
                <a href="{{ route('legal.cookies') }}">En savoir plus</a>
            </p>
        </div>

        <div class="brio-cookies-actions">
            <button type="button" x-on:click="acceptAll()" class="brio-btn brio-btn-accent">
                Tout accepter
            </button>
            <button type="button" x-on:click="rejectOptional()" class="brio-btn brio-btn-verre">
                Refuser les optionnels
            </button>
            <button type="button" x-on:click="open = !open" class="brio-btn brio-btn-nu"
                    :aria-expanded="open ? 'true' : 'false'">
                Personnaliser
            </button>
        </div>
    </div>

    <div x-show="open" x-cloak x-collapse class="brio-cookies-reglages">
        <label class="brio-cookies-ligne">
            <input type="checkbox" checked disabled>
            <span><strong>Essentiels</strong><em>session et securite — toujours actifs</em></span>
        </label>

        <label class="brio-cookies-ligne">
            <input type="checkbox" x-model="prefs.analytics">
            <span><strong>Mesure d'audience</strong><em>statistiques anonymes</em></span>
        </label>

        <label class="brio-cookies-ligne">
            <input type="checkbox" x-model="prefs.marketing">
            <span><strong>Personnalisation</strong><em>publicite adaptee</em></span>
        </label>

        <button type="button" x-on:click="savePrefs()" class="brio-btn brio-btn-accent brio-cookies-valider">
            Enregistrer mes choix
        </button>
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
                localStorage.setItem('brio_cookie_consent_v1', JSON.stringify(data));
                document.cookie = `brio_consent=${data.analytics?1:0}${data.marketing?1:0}; max-age=31536000; path=/; SameSite=Lax`;
            } catch (e) {}
        },

        read() {
            try {
                const raw = localStorage.getItem('brio_cookie_consent_v1');
                return raw ? JSON.parse(raw) : null;
            } catch (e) {
                return null;
            }
        },
    });
</script>
@endpush
