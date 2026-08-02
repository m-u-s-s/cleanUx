{{--
    « Ajoutez une photo, on affine l'estimation. »

    Ce bloc n'existait qu'en apparence : un champ fichier sans trait d'upload derrière lui, donc un
    fichier qui partait nulle part. Le client choisissait sa photo, lisait « Envoi en cours… », et
    rien n'était enregistré — ni erreur, ni trace, ni photo pour le prestataire.

    La COMPRESSION se fait dans le navigateur, avant l'envoi. Une photo de téléphone pèse
    couramment 4 à 6 Mo ; sur l'écran le plus rentable du produit, sur un réseau mobile, quatre
    photos brutes valent une minute d'attente et un abandon. Redimensionnée à 1600 px et réencodée
    en JPEG à 0,72, la même photo pèse quelques centaines de kilo-octets et montre exactement la
    même chose : c'est le mur qui compte, pas le grain du capteur.

    Jamais obligatoire : c'est un raccourci offert, pas un péage.
--}}
<section class="rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="photos-titre"
    x-data="orderPhotoUploader()" x-init="boot()">

    <h2 id="photos-titre" class="text-lg font-semibold text-slate-900">Une photo ?</h2>
    <p class="mt-0.5 text-sm text-slate-500">
        Elle vaut dix questions : le professionnel comprend en deux secondes, et l’estimation se resserre.
    </p>

    {{--
        La zone de dépôt. Le clic reste le chemin principal — le glisser-déposer n'existe pas au
        doigt, et c'est au doigt que ce parcours est d'abord conçu.
    --}}
    <label for="photo-input"
        x-on:dragover.prevent="hovering = true"
        x-on:dragleave.prevent="hovering = false"
        x-on:drop.prevent="hovering = false; ingest($event.dataTransfer.files)"
        x-bind:class="hovering ? 'border-slate-900 bg-slate-50' : 'border-slate-300 bg-slate-50/60'"
        class="mt-4 flex min-h-[104px] cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed p-5 text-center transition-colors">

        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-xl text-slate-500 shadow-sm"
            aria-hidden="true">+</span>

        <span class="mt-3 text-[15px] font-medium text-slate-900">Ajouter une photo</span>
        <span class="mt-1 text-sm text-slate-500 sm:hidden">Depuis l’appareil photo ou la galerie</span>
        <span class="mt-1 hidden text-sm text-slate-500 sm:block">Cliquez, ou déposez vos photos ici</span>

        {{--
            `capture="environment"` ouvre l'appareil arrière sur mobile : une étape de moins entre
            l'intention et le fichier.

            Le champ est masqué mais reste focusable (`sr-only`, pas `hidden`) : au clavier, il doit
            recevoir le focus et s'ouvrir par la barre d'espace comme n'importe quel champ fichier.
        --}}
        <input id="photo-input" type="file" x-ref="input" accept="image/*" capture="environment" multiple
            class="sr-only" x-on:change="ingest($event.target.files)">
    </label>

    {{-- Le travail de compression est ANNONCÉ : un écran figé sans explication se lit comme une panne. --}}
    <p x-show="working" x-cloak class="mt-3 text-sm text-slate-500" aria-live="polite">
        Préparation des photos…
    </p>

    {{-- Le champ Livewire réel, alimenté par le script après compression. --}}
    <input type="file" wire:model="photos" x-ref="livewire" multiple accept="image/*" class="hidden" tabindex="-1"
        aria-hidden="true">

    <p wire:loading wire:target="photos,attachPhotos" class="mt-3 text-sm text-slate-500" aria-live="polite">
        Envoi en cours…
    </p>

    @error('photos.*')
        <p class="mt-3 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">{{ $message }}</p>
    @enderror

    {{--
        Les photos déjà jointes. Sans aperçu, le client rejoint deux fois la même : il n'a aucun
        moyen de savoir ce qui est déjà parti.
    --}}
    @if ($this->attachedPhotos->isNotEmpty())
        <ul class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4">
            @foreach ($this->attachedPhotos as $photo)
                <li class="relative" wire:key="photo-{{ $photo->id }}">
                    <img src="{{ Storage::disk('public')->url($photo->path) }}"
                        alt="Photo jointe à votre demande"
                        class="aspect-square w-full rounded-xl object-cover">

                    <button type="button" wire:click="removePhoto({{ $photo->id }})"
                        aria-label="Retirer cette photo"
                        class="absolute -right-2 -top-2 flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-sm text-white shadow-sm hover:bg-slate-700">
                        ×
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</section>

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
    @endpush
@endonce
