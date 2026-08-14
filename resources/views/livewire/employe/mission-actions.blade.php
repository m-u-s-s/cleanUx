<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Actions mission</h3>
            <p class="text-sm text-slate-500">
                Statut actuel :
                <span class="font-medium text-slate-800">{{ $mission->status }}</span>
            </p>
        </div>
    </div>

    @if ($successMessage)
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ $successMessage }}
    </div>
    @endif

    @if ($errorMessage)
    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errorMessage }}
    </div>
    @endif

    <div class="grid gap-3 md:grid-cols-2">
        <button
            wire:click="setEnRoute"
            type="button"
            class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
            @disabled(! in_array($mission->status, ['planned', 'assigned']))
            >
            En route
        </button>

        <button
            wire:click="setArrived"
            type="button"
            class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
            @disabled(! in_array($mission->status, ['en_route', 'assigned']))
            >
            Arrivé
        </button>
    </div>

    {{--
        DEUX PARCOURS, ET ILS NE SE MÉLANGENT PAS.

        Une intervention se démarre et se clôture contre un code à six chiffres que le client donne :
        les deux personnes sont face à face, et le code atteste de cette rencontre. Une COURSE n'a
        rien de tel — le client est monté dans la voiture, et la preuve est la trace GPS qu'il suit
        lui-même. Afficher un champ de code à un conducteur au volant, c'est lui demander quelque
        chose que personne ne lui donnera.
    --}}
    @if ($this->estUneCourse())
        <div class="rounded-2xl border border-slate-200 p-4 space-y-3">
            <h4 class="font-medium text-slate-900">Course</h4>
            <p class="text-sm text-slate-500">
                Aucun code : la course démarre quand le client monte, et se termine à l’arrivée.
            </p>

            <div class="grid gap-3 md:grid-cols-2">
                <button
                    wire:click="demarrerLaCourse"
                    type="button"
                    class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
                    @disabled($mission->status !== 'arrived')
                    >
                    Client à bord
                </button>

                {{-- Le relevé précède l'appel : terminer encaisse, et le serveur confronte la
                     position au point de DÉPOSE. --}}
                <button
                    onclick="finishRideWithPosition(this)"
                    type="button"
                    class="rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
                    @disabled(! in_array($mission->status, ['started', 'paused']))
                    >
                    Terminer la course
                </button>
            </div>

            {{--
                L'ATTENTE AU POINT DE PRISE EN CHARGE.

                Le décompte vient du SERVEUR : un minuteur de page se remettrait à zéro d'un
                rechargement, et il suffirait d'actualiser pour déclarer un passager absent au bout
                de trois secondes. `wire:poll` rafraîchit la valeur ; c'est elle qui décide.
            --}}
            @php $attente = $this->secondesAvantAbsence(); @endphp
            @if ($attente !== null)
                <div class="rounded-xl bg-slate-50 px-4 py-3" wire:poll.15s>
                    @if ($attente > 0)
                        <p class="text-sm text-slate-600">
                            Attente du client — encore
                            <span class="font-semibold tabular-nums">{{ (int) ceil($attente / 60) }} min</span>
                            avant de pouvoir déclarer son absence.
                        </p>
                    @else
                        <p class="text-sm text-slate-700">Le client ne s’est pas présenté ?</p>
                        <button
                            wire:click="declarerClientAbsent"
                            wire:confirm="Déclarer que le client ne s’est pas présenté ? La course sera close et des frais lui seront appliqués."
                            type="button"
                            class="mt-2 rounded-xl border border-amber-400 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-900 transition hover:bg-amber-100">
                            Client absent
                        </button>
                    @endif
                </div>
            @endif
        </div>
    @else
    <form
        onsubmit="startMissionWithCode(event, {{ $mission->id }})"
        enctype="multipart/form-data"
        class="space-y-3">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">
                Code donné par le client
            </label>
            <input
                type="text"
                name="code"
                class="w-full rounded-xl border-slate-300"
                placeholder="Ex: 482913"
                required>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">
                Photos avant mission
            </label>
            <input
                type="file"
                name="photos_avant[]"
                accept="image/*"
                multiple
                class="w-full rounded-xl border border-slate-300 p-2">
        </div>

        <button
            type="submit"
            class="rounded-xl bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-700">
            Démarrer la mission
        </button>
    </form>
    <div class="rounded-2xl border border-slate-200 p-4 space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="font-medium text-slate-900">Commencer la mission</h4>
            @if ($generatedStartCode)
            <span class="rounded-lg bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-800">
                Code début : {{ $generatedStartCode }}
            </span>
            @endif
        </div>

        <div class="flex gap-2">
            <input
                wire:model.defer="startCode"
                type="text"
                inputmode="numeric"
                maxlength="6"
                placeholder="Code début"
                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:outline-none">
            <button
                wire:click="startMission"
                type="button"
                class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
                @disabled($mission->status !== 'arrived')
                >
                Commencer
            </button>
        </div>

        @error('startCode')
        <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="rounded-2xl border border-slate-200 p-4 space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="font-medium text-slate-900">Terminer la mission</h4>

            <button
                wire:click="prepareEndCode"
                type="button"
                class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                @disabled(! in_array($mission->status, ['started', 'paused']))
                >
                Générer code fin
            </button>
        </div>

        @if ($generatedEndCode)
        <div class="rounded-xl bg-slate-100 px-4 py-3 text-sm text-slate-700">
            <span class="font-semibold">Code fin :</span> {{ $generatedEndCode }}
        </div>
        @endif

        <div class="flex gap-2">
            <input
                wire:model.defer="endCode"
                type="text"
                inputmode="numeric"
                maxlength="6"
                placeholder="Code fin"
                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:outline-none">
            {{-- Le relevé précède l'appel : clôturer encaisse, et le serveur exige d'être sur place. --}}
            <button
                onclick="finishMissionWithPosition(this)"
                type="button"
                class="rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
                @disabled(! in_array($mission->status, ['started', 'paused']))
                >
                Terminer
            </button>
        </div>

        @error('endCode')
        <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
    @endif
</div>
<script>
    /**
     * Lit la position du navigateur, ou rend null.
     *
     * `null` couvre indifféremment le refus de permission, l'échec matériel et le délai dépassé :
     * la page ne décide pas de ce qu'il faut en conclure. C'est le serveur qui tranche — lui seul
     * n'est pas sur l'appareil de la personne contrôlée.
     *
     * `navigator.geolocation` est ABSENT hors contexte sécurisé : en HTTP simple, hors localhost,
     * les navigateurs le retirent purement et simplement. D'où la vérification d'existence avant
     * l'appel, sans quoi la page planterait au lieu de laisser le serveur expliquer.
     */
    async function readBrowserPosition() {
        if (!navigator.geolocation) {
            return null;
        }

        return new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition(
                (position) => resolve({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy_m: position.coords.accuracy ?? null,
                }),
                () => resolve(null),
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
            );
        });
    }

    /**
     * Clôture avec la position relevée à l'instant.
     *
     * Le bouton est neutralisé pendant le relevé : sans cela, un double clic partirait une seconde
     * fois avec les propriétés de la première tentative, et la lenteur du GPS passerait pour une
     * page figée.
     */
    async function finishMissionWithPosition(button) {
        // Le composant est retrouvé DEPUIS le bouton, et non via `@this` : ce script vit après
        // l'élément racine du composant, où `@this` ne se résout pas de façon fiable.
        const root = button.closest('[wire\\:id]');
        const component = root ? window.Livewire.find(root.getAttribute('wire:id')) : null;

        if (!component) {
            return;
        }

        button.disabled = true;

        try {
            const position = await readBrowserPosition();

            await component.set('lat', position ? position.lat : null, true);
            await component.set('lng', position ? position.lng : null, true);
            await component.set('accuracyM', position ? position.accuracy_m : null, true);

            await component.call('finishMission');
        } finally {
            button.disabled = false;
        }
    }

    /**
     * Même relevé, autre clôture : celle d'une course.
     *
     * La position part de la même façon — c'est le SERVEUR qui sait qu'il doit la confronter au
     * point de dépose et non au point de départ. Décider ici quel lieu comparer mettrait cette
     * règle sur l'appareil de la personne contrôlée.
     */
    async function finishRideWithPosition(button) {
        const root = button.closest('[wire\\:id]');
        const component = root ? window.Livewire.find(root.getAttribute('wire:id')) : null;

        if (!component) {
            return;
        }

        button.disabled = true;

        try {
            const position = await readBrowserPosition();

            await component.set('lat', position ? position.lat : null, true);
            await component.set('lng', position ? position.lng : null, true);

            await component.call('terminerLaCourse');
        } finally {
            button.disabled = false;
        }
    }

    async function startMissionWithCode(event, missionId) {
        event.preventDefault();

        const form = event.target;
        const formData = new FormData(form);

        if (navigator.geolocation) {
            await new Promise((resolve) => {
                navigator.geolocation.getCurrentPosition((position) => {
                    formData.append('lat', position.coords.latitude);
                    formData.append('lng', position.coords.longitude);
                    resolve();
                }, () => resolve(), {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0,
                });
            });
        }

        const response = await fetch(`/missions/${missionId}/start`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData,
        });

        const result = await response.json();

        if (!result.ok) {
            alert('Code invalide ou impossible de démarrer la mission.');
            return;
        }

        alert('Mission démarrée avec succès.');
        window.location.reload();
    }
</script>