<div class="py-8 max-w-xl mx-auto px-4">
    @if (! $booking)
        <div class="text-center py-16">
            <p class="text-slate-500">Booking introuvable.</p>
            <a href="{{ route('dashboard.client') }}" class="mt-4 inline-block text-indigo-600 hover:underline">← Retour</a>
        </div>
    @else
        <div class="rounded-2xl bg-white border shadow-sm p-6"
             x-data="stripeCheckoutWidget({{ json_encode([
                 'publishableKey' => $stripePublishableKey,
                 'clientSecret' => $clientSecret,
                 'bookingId' => $booking->id,
             ]) }})"
             x-init="boot()">

            <div class="mb-6 text-center">
                <p class="text-3xl mb-2">💳</p>
                <h1 class="text-2xl font-black text-slate-900">Paiement de votre mission</h1>
                <p class="text-sm text-slate-500 mt-1">Mission #{{ $booking->id }}</p>
                <p class="text-lg font-bold text-indigo-700 mt-2">
                    {{ number_format(((float) ($booking->devis_estime ?? 0)), 2, ',', ' ') }} €
                </p>
            </div>

            @if ($error)
                <div class="rounded-lg bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700 mb-4">
                    {{ $error }}
                </div>
            @endif

            @if (! $clientSecret)
                <button wire:click="startPayment"
                        @disabled($processing)
                        class="w-full rounded-lg bg-indigo-600 text-white py-3 text-sm font-bold hover:bg-indigo-500 disabled:opacity-50">
                    {{ $processing ? 'Préparation...' : 'Continuer vers le paiement' }}
                </button>
                <p class="text-xs text-slate-400 mt-3 text-center">Paiement sécurisé via Stripe — vous ne serez pas débité avant le démarrage de la mission.</p>
            @else
                <form id="payment-form" class="space-y-4">
                    <div id="payment-element" class="rounded-lg border p-3"></div>
                    <div id="payment-message" class="text-sm text-rose-600 hidden"></div>
                    <button type="submit" id="submit-button"
                            class="w-full rounded-lg bg-indigo-600 text-white py-3 text-sm font-bold hover:bg-indigo-500 disabled:opacity-50">
                        <span id="button-label">Confirmer la carte bancaire</span>
                        <span id="button-spinner" class="hidden">Traitement...</span>
                    </button>
                </form>
                <p class="text-xs text-slate-400 mt-3 text-center">3D Secure activé — votre banque peut demander une confirmation.</p>
            @endif

            <a href="{{ route('dashboard.client') }}" class="block text-center text-xs text-slate-500 hover:underline mt-4">Plus tard</a>
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
                        this.elements = this.stripe.elements({ clientSecret: this.clientSecret });
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
                                    msg.textContent = error.message;
                                    msg.classList.remove('hidden');
                                    document.getElementById('button-label').classList.remove('hidden');
                                    document.getElementById('button-spinner').classList.add('hidden');
                                    document.getElementById('submit-button').disabled = false;
                                    return;
                                }

                                // Setup ok — passer le payment method au Livewire
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
