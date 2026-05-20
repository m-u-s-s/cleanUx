<div class="py-8 max-w-2xl mx-auto px-4">
    <div class="flex justify-between items-center mb-6">
        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">Paiement</p>
            <h1 class="text-2xl font-black text-slate-900">Mes cartes bancaires</h1>
        </div>
        <button wire:click="startAdd" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-500">
            + Ajouter une carte
        </button>
    </div>

    @if ($error)
        <div class="rounded-lg bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700 mb-4">{{ $error }}</div>
    @endif

    @if ($newCardSetupIntent)
        <div class="rounded-2xl bg-white border shadow-sm p-6 mb-4"
             x-data="addCardWidget({ secret: '{{ $newCardSetupIntent }}', publishable: '{{ $stripeKey }}' })"
             x-init="boot()">
            <h2 class="text-sm font-bold text-slate-900 mb-3">Nouvelle carte</h2>
            <form id="add-card-form" class="space-y-3">
                <div id="payment-element" class="rounded-lg border p-3"></div>
                <div id="add-card-message" class="text-sm text-rose-600 hidden"></div>
                <button type="submit" class="w-full rounded-lg bg-indigo-600 text-white py-3 text-sm font-bold hover:bg-indigo-500">
                    Enregistrer la carte
                </button>
            </form>
        </div>
    @endif

    <div class="space-y-2">
        @forelse ($methods as $m)
            <div class="rounded-xl bg-white border shadow-sm p-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">💳</span>
                    <div>
                        <p class="font-semibold">{{ strtoupper($m['brand']) }} •••• {{ $m['last4'] }}</p>
                        <p class="text-xs text-slate-500">Expire {{ str_pad($m['exp_month'], 2, '0', STR_PAD_LEFT) }}/{{ $m['exp_year'] }}</p>
                    </div>
                    @if ($m['id'] === $defaultId)
                        <span class="ml-3 rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-xs font-semibold">Par défaut</span>
                    @endif
                </div>
                <div class="flex gap-2">
                    @if ($m['id'] !== $defaultId)
                        <button wire:click="setDefault('{{ $m['id'] }}')" class="text-indigo-600 hover:underline text-xs font-semibold">Définir par défaut</button>
                    @endif
                    <button wire:click="remove('{{ $m['id'] }}')" wire:confirm="Supprimer cette carte ?" class="text-rose-600 hover:underline text-xs font-semibold">Supprimer</button>
                </div>
            </div>
        @empty
            <div class="text-center py-12 text-slate-400">
                <p class="text-5xl mb-2">💳</p>
                <p>Aucune carte enregistrée pour le moment.</p>
            </div>
        @endforelse
    </div>

    @if ($newCardSetupIntent && $stripeKey)
        @push('scripts')
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            window.addCardWidget = (cfg) => ({
                stripe: null,
                elements: null,
                boot() {
                    if (!cfg.publishable) return;
                    this.stripe = Stripe(cfg.publishable);
                    this.elements = this.stripe.elements({ clientSecret: cfg.secret });
                    const pe = this.elements.create('payment');
                    pe.mount('#payment-element');
                    const form = document.getElementById('add-card-form');
                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const { error } = await this.stripe.confirmSetup({
                            elements: this.elements,
                            redirect: 'if_required',
                        });
                        if (error) {
                            const msg = document.getElementById('add-card-message');
                            msg.textContent = error.message;
                            msg.classList.remove('hidden');
                            return;
                        }
                        @this.set('newCardSetupIntent', null);
                        @this.dispatch('toast', { message: 'Carte ajoutée avec succès', type: 'success' });
                    });
                },
            });
        </script>
        @endpush
    @endif
</div>
