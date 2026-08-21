{{--
    LES SCRIPTS DU PARCOURS, DÉCLARÉS ICI ET PAS DANS LES MORCEAUX QUI LES UTILISENT.

    `@push('scripts')` n'atteint la pile du gabarit QU'AU PREMIER RENDU de la page. Or
    `partials/photos` et `partials/bundle` ne s'affichent qu'à une étape ultérieure, servie
    par Livewire en AJAX : leur `@push` arrivait après que la pile ait été rendue, et leurs
    fonctions n'atteignaient jamais le navigateur.

    Le symptôme, mesuré sur le parcours réel : « orderPhotoUploader is not defined », et
    l'envoi de photos — un RACCOURCI qui remplace des questions au client — ne marchait pas.

    Ce morceau-ci est inclus SANS condition depuis la vue racine du composant, donc rendu
    avec la page. Les morceaux gardent leur `x-data`, ils ne portent plus leur définition.
--}}
@once
    @push('scripts')
    <script>
        window.orderPhotoUploader = () => ({
            hovering: false,
            working: false,

            boot() {
                // Rien a amorcer : l'etat vit dans Alpine, le transfert dans Livewire.
            },

            /**
             * Redimensionne et reencode avant l'envoi.
             *
             * Une photo de telephone pese 4 a 6 Mo. Sur un reseau mobile, quatre photos brutes
             * valent une minute d'attente sur l'ecran le plus rentable du produit. A 1600 px de
             * cote et en JPEG 0,72, la meme photo montre exactement la meme chose pour quelques
             * centaines de kilo-octets : c'est le mur qui compte, pas le grain du capteur.
             *
             * Si quoi que ce soit echoue — format exotique, canvas refuse, memoire — on envoie le
             * fichier D'ORIGINE. La compression est un confort ; la perdre ne doit jamais faire
             * perdre la photo.
             */
            async ingest(fileList) {
                const files = Array.from(fileList || []).filter((f) => f.type.startsWith('image/'));

                if (! files.length) {
                    return;
                }

                this.working = true;

                const prepared = [];
                for (const file of files) {
                    try {
                        prepared.push(await this.shrink(file));
                    } catch (e) {
                        prepared.push(file);
                    }
                }

                // On repasse par un DataTransfer : c'est la seule facon d'alimenter un input
                // fichier par programme, et donc de laisser Livewire faire son transfert habituel.
                const bag = new DataTransfer();
                prepared.forEach((f) => bag.items.add(f));

                /*
                 * On joint SEULEMENT quand le transfert est fini.
                 *
                 * Appeler `attachPhotos` juste apres l'evenement `change` le ferait partir avant
                 * que le fichier n'ait atteint le serveur : la requete arriverait sur un tableau
                 * vide, et la photo serait perdue sans que rien ne le signale — exactement le
                 * defaut que ce bloc corrige.
                 */
                this.$refs.livewire.addEventListener(
                    'livewire-upload-finish',
                    () => this.$wire.attachPhotos(),
                    { once: true },
                );

                this.$refs.livewire.files = bag.files;
                this.$refs.livewire.dispatchEvent(new Event('change', { bubbles: true }));

                this.working = false;
                this.$refs.input.value = '';
            },

            shrink(file) {
                return new Promise((resolve, reject) => {
                    const url = URL.createObjectURL(file);
                    const img = new Image();

                    img.onload = () => {
                        URL.revokeObjectURL(url);

                        const max = 1600;
                        const scale = Math.min(1, max / Math.max(img.width, img.height));

                        // Deja assez petite : la reencoder ne ferait que degrader pour rien.
                        if (scale === 1 && file.size < 900 * 1024) {
                            resolve(file);
                            return;
                        }

                        const canvas = document.createElement('canvas');
                        canvas.width = Math.round(img.width * scale);
                        canvas.height = Math.round(img.height * scale);
                        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);

                        canvas.toBlob((blob) => {
                            if (! blob) {
                                reject(new Error('encodage impossible'));
                                return;
                            }

                            resolve(new File(
                                [blob],
                                file.name.replace(/\.[^.]+$/, '') + '.jpg',
                                { type: 'image/jpeg', lastModified: Date.now() },
                            ));
                        }, 'image/jpeg', 0.72);
                    };

                    img.onerror = () => {
                        URL.revokeObjectURL(url);
                        reject(new Error('image illisible'));
                    };

                    img.src = url;
                });
            },
        });
    </script>
<script>
    window.bundleSorter = () => ({
        dragged: null,

        boot() {
            const root = this.$el;

            root.addEventListener('dragstart', (e) => {
                this.dragged = e.target.closest('[data-item-id]');
                if (this.dragged) {
                    e.dataTransfer.effectAllowed = 'move';
                    this.dragged.style.opacity = '0.4';
                }
            });

            root.addEventListener('dragend', () => {
                if (this.dragged) {
                    this.dragged.style.opacity = '';
                }
                this.dragged = null;
            });

            root.addEventListener('dragover', (e) => {
                e.preventDefault();
                const over = e.target.closest('[data-item-id]');

                if (! over || ! this.dragged || over === this.dragged) {
                    return;
                }

                const box = over.getBoundingClientRect();
                const after = (e.clientY - box.top) > (box.height / 2);
                over.parentNode.insertBefore(this.dragged, after ? over.nextSibling : over);
            });

            root.addEventListener('drop', (e) => {
                e.preventDefault();
                this.commit();
            });
        },

        /** Le même déplacement, au clavier : c'est la seule voie accessible. */
        nudge(itemId, direction) {
            const items = Array.from(this.$el.querySelectorAll('[data-item-id]'));
            const index = items.findIndex((el) => el.dataset.itemId === String(itemId));
            const target = index + direction;

            if (index < 0 || target < 0 || target >= items.length) {
                return;
            }

            const ids = items.map((el) => el.dataset.itemId);
            [ids[index], ids[target]] = [ids[target], ids[index]];

            this.$wire.reorderServices(ids);
        },

        commit() {
            // Le serveur revalide : une dépendance de chantier ne se contourne pas depuis le
            // navigateur, et il renvoie un refus lisible plutôt qu'un ordre corrigé en douce.
            this.$wire.reorderServices(
                Array.from(this.$el.querySelectorAll('[data-item-id]')).map((el) => el.dataset.itemId),
            );
        },
    });
</script>
    @endpush
@endonce
