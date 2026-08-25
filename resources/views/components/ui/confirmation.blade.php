{{--
    LA CONFIRMATION DE VERRE — une seule, pour tout le produit.

    Sept boutons portaient un `onclick="return confirm('...')"`. La boite du navigateur ne
    connait ni le theme, ni la langue de la page, ni la gravite de ce qu'elle demande :
    « Reevoquer definitivement ce token ? » et « Cloturer la periode ? » s'y ressemblent
    exactement, alors que l'une se rattrape et l'autre non.

    COMMENT UN BOUTON L'APPELLE. Il emet un evenement plutot que d'inclure une modale :

        <button x-data x-on:click.prevent="$dispatch('brio-confirmer', {
            message: 'Revoquer definitivement ce token ?',
            ton: 'danger',
            appel: 'revoke(7)',
        })">

    `appel` est le nom de la methode Livewire a declencher si l'on accepte. Le composant
    remonte au `[wire:id]` le plus proche du bouton : sans cela il faudrait nommer le
    composant a chaque appel, et le premier renommage casserait tout en silence.

    POURQUOI PAS `wire:confirm`. Livewire l'implemente avec `window.confirm` — la meme boite
    du navigateur. Le remplacer par lui n'aurait rien change a ce qu'on voit.
--}}
<div
    x-data="{
        ouvert: false,
        message: '',
        ton: 'neutre',
        appel: '',
        composant: null,

        demander(detail, source) {
            this.message = detail.message || '';
            this.ton = detail.ton || 'neutre';
            this.appel = detail.appel || '';

            /*
             * Le composant Livewire est celui qui CONTIENT le bouton. Le chercher ici plutot
             * que de le faire nommer par l'appelant evite qu'un renommage casse sept sites
             * d'appel sans que rien ne le signale.
             */
            const hote = source?.closest('[wire\\:id]');
            this.composant = hote ? hote.getAttribute('wire:id') : null;

            this.ouvert = true;

            this.$nextTick(() => this.$refs.refuser?.focus());
        },

        accepter() {
            const appel = this.appel;
            const id = this.composant;

            this.fermer();

            if (! appel || ! id) return;

            const cible = window.Livewire?.find(id);

            if (! cible) return;

            /*
             * « methode(1, 'x') » se decoupe ici plutot que d'etre evalue : passer par `eval`
             * ferait d'un libelle une porte d'execution.
             */
            const decoupe = appel.match(/^\s*([A-Za-z_$][\w$]*)\s*(?:\((.*)\))?\s*$/);

            if (! decoupe) return;

            let arguments_ = [];

            if (decoupe[2] !== undefined && decoupe[2].trim() !== '') {
                try {
                    arguments_ = JSON.parse('[' + decoupe[2] + ']');
                } catch (e) {
                    return;
                }
            }

            cible.call(decoupe[1], ...arguments_);
        },

        fermer() {
            this.ouvert = false;
            this.appel = '';
            this.composant = null;
        },
    }"
    x-on:brio-confirmer.window="demander($event.detail, $event.target)"
    x-on:keydown.escape.window="fermer()"
>
    <template x-if="ouvert">
        <div class="brio-modal-fond grid place-items-center p-4" x-on:click.self="fermer()">
            <div
                class="brio-modal"
                :class="ton === 'danger' && 'brio-modal-danger'"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="brio-confirmer-titre"
            >
                <h2 id="brio-confirmer-titre" class="brio-modal-titre" x-text="ton === 'danger' ? '{{ __('Action irréversible') }}' : '{{ __('Confirmer') }}'"></h2>

                <p class="brio-modal-texte" x-text="message"></p>

                <div class="brio-modal-actions">
                    {{-- LE REFUS PORTE LE FOCUS. Une modale qui s'ouvre sur son bouton
                         destructeur transforme une touche Entree en action definitive. --}}
                    <button type="button" x-ref="refuser" class="brio-btn brio-btn-nu" x-on:click="fermer()">
                        {{ __('Annuler') }}
                    </button>

                    <button
                        type="button"
                        class="brio-btn brio-btn-accent"
                        x-on:click="accepter()"
                    >
                        {{ __('Confirmer') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
