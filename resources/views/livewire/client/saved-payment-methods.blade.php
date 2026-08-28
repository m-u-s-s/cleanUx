{{--
    LES CARTES, EN VRAIES CARTES.

    Un moyen de paiement s'affichait en emoji suivi d'une ligne de texte. Il devient
    l'objet qu'il represente : bandeau de marque, puce, numero masque, echeance. Le
    client reconnait SA carte d'un coup d'oeil, sans lire.

    La suppression passait par `wire:confirm`, qui ouvre la boite grise du navigateur.
    Elle passe par une modale de verre — meme garde-fou, sans la rupture visuelle.
--}}
{{-- SECTION, PLUS PAGE : le portefeuille la monte, les cartes n'ont plus de route a elles. --}}
<div x-data="{ aSupprimer: null, marque: '', quatre: '' }">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('Mes moyens de paiement') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Vos cartes enregistrées, pour payer sans les ressaisir.') }}</p>
        </div>

        <button type="button" wire:click="startAdd" class="brio-btn brio-btn-accent">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" class="h-4 w-4" aria-hidden="true">
                <line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Ajouter une carte
        </button>
    </div>

    @if ($error)
        <p class="brio-alerte brio-alerte-danger" role="alert">{{ $error }}</p>
    @endif

    @if ($newCardSetupIntent)
        <div class="brio-card mb-6 p-6"
             x-data="addCardWidget({ secret: '{{ $newCardSetupIntent }}', publishable: '{{ $stripeKey }}' })"
             x-init="boot()">
            <h2 class="brio-ui-title text-sm font-bold">Nouvelle carte</h2>

            <form id="add-card-form" class="mt-4 space-y-4">
                <div id="payment-element" class="rounded-xl border border-[color:var(--glass-border)] bg-[color:var(--glass-bg-faint)] p-3"></div>
                <div id="add-card-message" class="hidden text-sm" style="color: var(--brio-danger)"></div>

                <button type="submit" class="brio-btn brio-btn-accent w-full">Enregistrer la carte</button>
            </form>
        </div>
    @endif

    <div class="brio-cartes brio-stagger">
        @forelse ($methods as $m)
            @php($marque = strtolower($m['brand'] ?? ''))

            <article class="brio-carte brio-carte-{{ in_array($marque, ['visa', 'mastercard', 'amex'], true) ? $marque : 'neutre' }}">
                <span class="brio-carte-lustre" aria-hidden="true"></span>

                <header class="brio-carte-tete">
                    <span class="brio-carte-puce" aria-hidden="true"></span>

                    @if ($m['id'] === $defaultId)
                        <span class="brio-carte-defaut">Par défaut</span>
                    @endif
                </header>

                <p class="brio-carte-numero">
                    <span aria-hidden="true">•••• •••• ••••</span>
                    <strong>{{ $m['last4'] }}</strong>
                    <span class="sr-only">Carte se terminant par {{ $m['last4'] }}</span>
                </p>

                <footer class="brio-carte-pied">
                    <span class="brio-carte-echeance">
                        <em>Expire</em>
                        {{ str_pad((string) $m['exp_month'], 2, '0', STR_PAD_LEFT) }}/{{ substr((string) $m['exp_year'], -2) }}
                    </span>

                    <span class="brio-carte-marque">{{ strtoupper($m['brand']) }}</span>
                </footer>

                <div class="brio-carte-actions">
                    @if ($m['id'] !== $defaultId)
                        <button type="button" wire:click="setDefault('{{ $m['id'] }}')" class="brio-btn brio-btn-verre">
                            Définir par défaut
                        </button>
                    @endif

                    <button
                        type="button"
                        class="brio-btn brio-btn-nu"
                        @click="aSupprimer = '{{ $m['id'] }}'; marque = '{{ strtoupper($m['brand']) }}'; quatre = '{{ $m['last4'] }}'"
                    >
                        Supprimer
                    </button>
                </div>
            </article>
        @empty
            <div class="brio-vide">
                <span class="brio-vide-icone" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="5" width="20" height="14" rx="3" /><line x1="2" y1="10" x2="22" y2="10" />
                    </svg>
                </span>
                <p class="brio-vide-titre">Aucune carte enregistrée</p>
                <p class="brio-vide-texte">Ajoutez une carte pour régler vos interventions en un geste.</p>
            </div>
        @endforelse
    </div>

    {{-- La confirmation de suppression : une modale de verre, la ou `wire:confirm`
         ouvrait la boite grise du navigateur. --}}
    <div x-show="aSupprimer" x-cloak class="brio-modal-fond grid place-items-center p-4"
         @keydown.escape.window="aSupprimer = null"
         @click.self="aSupprimer = null"
         role="dialog" aria-modal="true" aria-labelledby="titre-suppression">
        <div class="brio-modal brio-modal-danger">
            <h2 id="titre-suppression" class="brio-modal-titre">Retirer cette carte ?</h2>

            <p class="brio-modal-texte">
                <span x-text="marque"></span> se terminant par <span x-text="quatre"></span>.
                Vos interventions en cours ne sont pas annulées, mais un règlement automatique
                pourrait échouer si aucune autre carte n'est enregistrée.
            </p>

            <div class="brio-modal-actions">
                <button type="button" class="brio-btn brio-btn-nu" @click="aSupprimer = null">Annuler</button>
                <button type="button" class="brio-btn brio-btn-danger"
                        @click="$wire.remove(aSupprimer); aSupprimer = null">
                    Retirer la carte
                </button>
            </div>
        </div>
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
