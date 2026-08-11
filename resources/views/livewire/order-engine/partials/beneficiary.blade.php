{{--
    RÉSERVER POUR UN PROCHE (E1).

    Le client paye, quelqu'un d'autre reçoit. Ce cas se bricolait dans le commentaire libre : le
    professionnel arrivait en demandant M. Dupont et trouvait sa mère, qui n'attendait personne —
    l'intervention commençait par un malentendu, parfois par une porte qui ne s'ouvre pas.

    ÉTIQUETTES ASSOCIÉES PAR `for`/`id`, jamais par simple imbrication : une étiquette qui enveloppe
    son champ fonctionne aujourd'hui et casse dès qu'on insère un conteneur entre les deux, sans que
    rien ne le signale. Le garde-fou du moteur de commande l'exige, et il a raison.

    REPLIÉ PAR DÉFAUT, et facultatif. La commande la plus ordinaire est celle qu'on passe pour soi :
    imposer cette étape ajouterait un obstacle à tout le monde pour servir une minorité.
--}}
<section class="rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="beneficiaire-titre">
    <details @if ($beneficiaryName !== '') open @endif>
        <summary class="cursor-pointer list-none">
            <h2 id="beneficiaire-titre" class="inline text-lg font-semibold text-slate-900">
                C'est pour quelqu'un d'autre ?
            </h2>
            <p class="mt-0.5 text-sm text-slate-500">
                Indiquez qui accueillera le professionnel — facultatif.
            </p>
        </summary>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label for="beneficiaire-nom" class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Nom de la personne</span>
                <input
                    id="beneficiaire-nom"
                    type="text"
                    wire:model.blur="beneficiaryName"
                    placeholder="Prénom et nom"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-[15px] text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                >
                @error('beneficiaryName')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label for="beneficiaire-telephone" class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Son téléphone</span>
                <input
                    id="beneficiaire-telephone"
                    type="tel"
                    wire:model.blur="beneficiaryPhone"
                    placeholder="+32 4xx xx xx xx"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-[15px] text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                >
                @error('beneficiaryPhone')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>
        </div>

        <label for="beneficiaire-note" class="mt-4 block">
            <span class="mb-1 block text-sm font-semibold text-slate-900">À savoir</span>
            <input
                id="beneficiaire-note"
                type="text"
                wire:model.blur="beneficiaryNote"
                placeholder="Ma mère a 82 ans, sonnez longtemps."
                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-[15px] text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
            >
            @error('beneficiaryNote')
            <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
            @enderror
        </label>

        <button
            type="button"
            wire:click="enregistrerLeBeneficiaire"
            class="mt-4 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
        >
            Enregistrer
        </button>

        <p class="mt-3 text-xs text-slate-500">
            Le professionnel verra ce nom sur sa fiche, et pourra suivre l'intervention avec cette
            personne.
        </p>
    </details>
</section>
