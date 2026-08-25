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

    ET `wire:confirm` PASSE PAR ICI AUSSI, sans qu'aucune vue ne soit touchee. Livewire pose
    `el.__livewire_confirm = (action, instead) => …` : une API a RAPPELS, pas a valeur de
    retour. Elle accepte donc une modale, la ou `window.confirm()` — synchrone — ne le
    pourrait pas. L'interception vit dans `resources/js/confirmation-livewire.js` ; ce
    composant recoit les deux fonctions dans `detail.action` et `detail.instead`.
--}}
<div
    x-data="{
        ouvert: false,
        message: '',
        ton: 'neutre',
        appel: '',
        composant: null,
        /*
         * LA VOIE PAR RAPPELS, pour `wire:confirm`.
         *
         * Livewire pose `el.__livewire_confirm = (action, instead) => …` : une API a
         * RAPPELS, pas a valeur de retour. Elle accepte donc parfaitement une modale, la
         * ou `window.confirm()` — synchrone — ne le pourrait pas. `resources/js/
         * confirmation-livewire.js` l'intercepte et nous transmet ces deux fonctions.
         */
        action: null,
        refus: null,

        demander(detail, source) {
            this.message = detail.message || '';
            this.ton = detail.ton || 'neutre';
            this.appel = detail.appel || '';
            this.action = typeof detail.action === 'function' ? detail.action : null;
            this.refus = typeof detail.instead === 'function' ? detail.instead : null;

            /*
             * Le composant Livewire est celui qui CONTIENT le bouton. Le chercher ici plutot
             * que de le faire nommer par l'appelant evite qu'un renommage casse sept sites
             * d'appel sans que rien ne le signale.
             *
             * `typeof … === 'function'` et non `source?.closest` : un evenement emis depuis
             * `window` a pour cible `window`, qui n'a pas de `closest`. L'appel levait alors
             * AVANT `this.ouvert = true`, et la modale ne s'ouvrait jamais — en silence.
             */
            const hote = typeof source?.closest === 'function' ? source.closest('[wire\\:id]') : null;
            this.composant = hote ? hote.getAttribute('wire:id') : null;

            this.ouvert = true;

            this.$nextTick(() => this.$refs.refuser?.focus());
        },

        accepter() {
            const appel = this.appel;
            const id = this.composant;
            const rappel = this.action;

            this.fermer();

            // La voie par rappels est prioritaire : elle vient de Livewire lui-meme.
            if (rappel) {
                rappel();

                return;
            }

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

        /*
         * REFUSER, C'EST AUSSI REPONDRE. Livewire attend `instead()` sur le chemin du refus ;
         * ne rien appeler laisserait son binding croire que la question est encore ouverte.
         */
        refuser() {
            const rappel = this.refus;

            this.fermer();

            if (rappel) rappel();
        },

        fermer() {
            this.ouvert = false;
            this.appel = '';
            this.composant = null;
            this.action = null;
            this.refus = null;
        },
    }"
    {{-- `preventDefault()` DIT « je suis la ». L'interception de `wire:confirm` rend la
         main a Livewire si l'evenement lui revient non annule : sans ce signal, un
         bouton place sur une mise en page qui ne monte pas ce composant ne ferait
         plus rien du tout, en silence. --}}
    x-on:brio-confirmer.window="$event.preventDefault(); demander($event.detail, $event.target)"
    x-on:keydown.escape.window="refuser()"
>
    <template x-if="ouvert">
        <div class="brio-modal-fond grid place-items-center p-4" x-on:click.self="refuser()">
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
                    <button type="button" x-ref="refuser" class="brio-btn brio-btn-nu" x-on:click="refuser()">
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
