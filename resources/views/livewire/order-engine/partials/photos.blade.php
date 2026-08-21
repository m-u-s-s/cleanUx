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
