{{--
  Phase 8 — Bandeau d'invitation à installer la PWA.

  Comportement :
    - Android/Chrome/Edge : bouton "Installer" via beforeinstallprompt
    - iOS Safari : instructions manuelles (Partager → Sur l'écran d'accueil)
    - Si déjà installé (mode standalone) : ne montre rien
    - Persistance dismiss en localStorage 30 jours
--}}

<div x-data="pwaInstallPrompt()"
     x-init="init()"
     x-show="showPrompt"
     x-cloak
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="translate-y-full opacity-0"
     x-transition:enter-end="translate-y-0 opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="translate-y-0 opacity-100"
     x-transition:leave-end="translate-y-full opacity-0"
     class="fixed bottom-20 left-2 right-2 z-50 sm:bottom-4 sm:left-auto sm:right-4 sm:max-w-sm">

    {{-- Card Android/Desktop --}}
    <div x-show="mode === 'native'" class="rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100 text-2xl">📱</div>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-bold text-slate-900">Installer CleanUx</h3>
                <p class="mt-0.5 text-xs text-slate-600">
                    Accès rapide depuis ton écran d'accueil, mode hors-ligne, notifications push.
                </p>
                <div class="mt-2 flex gap-2">
                    <button @click="install()"
                            class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                        Installer
                    </button>
                    <button @click="dismiss()"
                            class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-200">
                        Plus tard
                    </button>
                </div>
            </div>
            <button @click="dismiss()" class="text-slate-400 hover:text-slate-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Card iOS (instructions manuelles) --}}
    <div x-show="mode === 'ios'" class="rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100 text-2xl">🍎</div>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-bold text-slate-900">Ajouter à l'écran d'accueil</h3>
                <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                    1. Appuie sur
                    <svg class="inline h-3.5 w-3.5 align-text-bottom" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2L8 6h3v8h2V6h3l-4-4zm-7 9v9c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2v-9h-2v9H7v-9H5z"/>
                    </svg>
                    Partager dans Safari<br>
                    2. Choisis <strong>« Sur l'écran d'accueil »</strong>
                </p>
                <button @click="dismiss()"
                        class="mt-2 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-200">
                    Compris
                </button>
            </div>
            <button @click="dismiss()" class="text-slate-400 hover:text-slate-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function pwaInstallPrompt() {
        return {
            showPrompt: false,
            mode: null,
            dismissedKey: 'cleanux_pwa_dismissed_v1',

            init() {
                if (window.cleanuxPwa?.isStandalone()) return;

                const dismissed = localStorage.getItem(this.dismissedKey);
                if (dismissed) {
                    const dismissedAt = parseInt(dismissed, 10);
                    if (Date.now() - dismissedAt < 30 * 24 * 60 * 60 * 1000) return;
                }

                if (window.cleanuxPwa?.isIOS()) {
                    setTimeout(() => {
                        this.mode = 'ios';
                        this.showPrompt = true;
                    }, 5000);
                    return;
                }

                window.addEventListener('pwa:install-available', () => {
                    this.mode = 'native';
                    this.showPrompt = true;
                });

                if (window.cleanuxPwa?.canInstall()) {
                    this.mode = 'native';
                    this.showPrompt = true;
                }
            },

            async install() {
                if (window.cleanuxPwa?.canInstall()) {
                    const accepted = await window.cleanuxPwa.promptInstall();
                    this.showPrompt = false;
                    if (!accepted) {
                        this.dismiss();
                    }
                }
            },

            dismiss() {
                this.showPrompt = false;
                localStorage.setItem(this.dismissedKey, Date.now().toString());
            },
        };
    }
</script>
@endpush
