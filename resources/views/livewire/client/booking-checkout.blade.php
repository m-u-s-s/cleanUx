<div class="py-8 max-w-xl mx-auto px-4">
    @if (! $booking)
        <div class="text-center py-16">
            <div class="grid h-14 w-14 mx-auto place-items-center rounded-2xl bg-slate-100 text-slate-400 mb-4">
                <x-ui.icon name="exclamation-triangle" class="w-6 h-6" />
            </div>
            <p class="text-slate-700 font-semibold">Booking introuvable</p>
            <a href="{{ route('client.dashboard') }}" class="mt-4 inline-flex items-center gap-1.5 text-brand-700 hover:text-brand-800 text-sm font-semibold">
                <x-ui.icon name="arrow-left" class="w-4 h-4" />
                <span>Retour</span>
            </a>
        </div>
    @else
        {{-- Trust bar --}}
        <div class="mb-5 flex items-center justify-center gap-4 text-xs text-slate-500">
            <span class="inline-flex items-center gap-1.5">
                <x-ui.icon name="shield-check" class="w-3.5 h-3.5 text-emerald-500" />
                <span>Stripe · SCA</span>
            </span>
            <span class="text-slate-300">·</span>
            <span class="inline-flex items-center gap-1.5">
                <x-ui.icon name="lock-closed" class="w-3.5 h-3.5 text-emerald-500" />
                <span>SSL chiffré</span>
            </span>
            <span class="text-slate-300">·</span>
            <span class="inline-flex items-center gap-1.5">
                <x-ui.icon name="badge-check" class="w-3.5 h-3.5 text-emerald-500" />
                <span>3D Secure</span>
            </span>
        </div>

        <div class="rounded-2xl bg-white border border-slate-200/80 shadow-soft p-6 sm:p-8"
             x-data="stripeCheckoutWidget({{ json_encode([
                 'publishableKey' => $stripePublishableKey,
                 'clientSecret' => $clientSecret,
                 'bookingId' => $booking->id,
             ]) }})"
             x-init="boot()">

            <div class="mb-6 text-center">
                <div class="grid h-12 w-12 mx-auto place-items-center rounded-2xl bg-brand-50 text-brand-600 mb-3">
                    <x-ui.icon name="credit-card" class="w-6 h-6" />
                </div>
                <h1 class="text-xl font-bold tracking-tight text-slate-900">Paiement de votre mission</h1>
                <p class="text-xs text-slate-500 mt-1">Mission #{{ $booking->id }}</p>

                <div class="mt-4 inline-flex items-baseline gap-1">
                    <span class="text-3xl font-bold text-slate-900">
                        {{ number_format(((float) ($booking->devis_estime ?? 0)), 2, ',', ' ') }}
                    </span>
                    <span class="text-lg font-semibold text-slate-500">€</span>
                </div>
                <p class="text-xs text-slate-500 mt-1">Total estimé · TVA incluse</p>
            </div>

            @if ($error)
                <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 p-3.5 text-sm text-rose-700 inline-flex items-start gap-2">
                    <x-ui.icon name="exclamation-triangle" class="w-4 h-4 mt-0.5 flex-shrink-0" />
                    <span>{{ $error }}</span>
                </div>
            @endif

            @if (! $clientSecret)
                <button wire:click="startPayment"
                        @disabled($processing)
                        class="w-full rounded-xl bg-brand-600 text-white py-3 text-sm font-semibold hover:bg-brand-700 active:bg-brand-800 disabled:opacity-50 disabled:cursor-not-allowed transition inline-flex items-center justify-center gap-2">
                    @if($processing)
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        <span>Préparation…</span>
                    @else
                        <span>Continuer vers le paiement</span>
                        <x-ui.icon name="arrow-right" class="w-4 h-4" />
                    @endif
                </button>

                <p class="text-xs text-slate-400 mt-3 text-center">
                    Paiement sécurisé via Stripe. Pas de débit avant le démarrage de la mission.
                </p>
            @else
                <form id="payment-form" class="space-y-4">
                    <div id="payment-element" class="rounded-xl border border-slate-200 p-3.5 bg-slate-50/30"></div>
                    <div id="payment-message" class="text-sm text-rose-600 hidden inline-flex items-start gap-2">
                        <x-ui.icon name="exclamation-triangle" class="w-4 h-4 mt-0.5 flex-shrink-0" />
                        <span id="payment-message-text"></span>
                    </div>
                    <button type="submit" id="submit-button"
                            class="w-full rounded-xl bg-brand-600 text-white py-3 text-sm font-semibold hover:bg-brand-700 active:bg-brand-800 disabled:opacity-50 disabled:cursor-not-allowed transition inline-flex items-center justify-center gap-2">
                        <span id="button-label" class="inline-flex items-center gap-2">
                            <x-ui.icon name="lock-closed" class="w-4 h-4" />
                            <span>Confirmer la carte bancaire</span>
                        </span>
                        <span id="button-spinner" class="hidden inline-flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                            <span>Traitement…</span>
                        </span>
                    </button>
                </form>
                <p class="text-xs text-slate-400 mt-3 text-center">
                    3D Secure activé. Votre banque peut demander une confirmation.
                </p>
            @endif

            <a href="{{ route('client.dashboard') }}" class="block text-center text-xs text-slate-500 hover:text-slate-700 mt-4 transition">
                Plus tard
            </a>
        </div>

        {{-- Garanties --}}
        <div class="mt-6 grid grid-cols-3 gap-3 text-center">
            <div class="rounded-xl border border-slate-200/70 bg-white p-3">
                <x-ui.icon name="shield-check" class="w-5 h-5 mx-auto text-emerald-600" />
                <p class="mt-1.5 text-xs font-semibold text-slate-700">Assuré</p>
            </div>
            <div class="rounded-xl border border-slate-200/70 bg-white p-3">
                <x-ui.icon name="check-circle" class="w-5 h-5 mx-auto text-emerald-600" />
                <p class="mt-1.5 text-xs font-semibold text-slate-700">Validé</p>
            </div>
            <div class="rounded-xl border border-slate-200/70 bg-white p-3">
                <x-ui.icon name="badge-check" class="w-5 h-5 mx-auto text-emerald-600" />
                <p class="mt-1.5 text-xs font-semibold text-slate-700">RGPD</p>
            </div>
        </div>

        @if ($clientSecret && $stripePublishableKey)
            @push('scripts')
            <script src="https://js.stripe.com/v3/"></script>
            <script>
                window.stripeCheckoutWidget = (cfg) => ({
                    publishableKey: cfg.publishableKey,
                    clientSecret: cfg.clientSecret,
                    bookingId: cfg.bookingId,
                    stripe: null,
                    elements: null,

                    boot() {
                        if (!this.publishableKey) {
                            console.warn('Stripe publishable key missing');
                            return;
                        }
                        this.stripe = Stripe(this.publishableKey);
                        this.elements = this.stripe.elements({
                            clientSecret: this.clientSecret,
                            // Le formulaire de carte suit le theme de la page : sans cela, il
                            // restait blanc sur fond sombre.
                            appearance: (() => {
                                const sombre = document.documentElement.classList.contains('dark');

                                return {
                                    theme: sombre ? 'night' : 'stripe',
                                    variables: {
                                        colorPrimary: '#4f46e5',
                                        colorBackground: sombre ? window.brioJeton('--cx-panel', '#111a2e') : '#ffffff',
                                        colorText: sombre ? window.brioJeton('--cx-text', '#e8eefc') : window.brioJeton('--brio-ink', '#0f172a'),
                                        colorDanger: '#e11d48',
                                        fontFamily: 'Inter, system-ui, sans-serif',
                                        borderRadius: window.brioJeton('--cx-radius-sm', '10px'),
                                    }
                                };
                            })()
                        });
                        const paymentElement = this.elements.create('payment');
                        paymentElement.mount('#payment-element');

                        const form = document.getElementById('payment-form');
                        if (form) {
                            form.addEventListener('submit', async (e) => {
                                e.preventDefault();
                                document.getElementById('button-label').classList.add('hidden');
                                document.getElementById('button-spinner').classList.remove('hidden');
                                document.getElementById('submit-button').disabled = true;

                                const { error, setupIntent } = await this.stripe.confirmSetup({
                                    elements: this.elements,
                                    redirect: 'if_required',
                                });

                                if (error) {
                                    const msg = document.getElementById('payment-message');
                                    const txt = document.getElementById('payment-message-text');
                                    if (txt) txt.textContent = error.message;
                                    else msg.textContent = error.message;
                                    msg.classList.remove('hidden');
                                    document.getElementById('button-label').classList.remove('hidden');
                                    document.getElementById('button-spinner').classList.add('hidden');
                                    document.getElementById('submit-button').disabled = false;
                                    return;
                                }

                                if (setupIntent && setupIntent.payment_method) {
                                    @this.call('confirmAuthorization', setupIntent.payment_method);
                                }
                            });
                        }
                    },
                });
            </script>
            @endpush
        @endif
    @endif
</div>
