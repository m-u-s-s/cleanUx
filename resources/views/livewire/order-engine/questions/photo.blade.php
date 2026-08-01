{{--
    La photo vaut dix questions.

    `capture="environment"` ouvre l'appareil arrière directement sur mobile : une étape de moins
    entre l'intention et le fichier. Jamais obligatoire — c'est un raccourci offert au client, pas
    un péage sur son chemin.
--}}
<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-center">
    <label class="block cursor-pointer">
        <input type="file" wire:model="value" accept="image/*" capture="environment" multiple class="sr-only">
        <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-white text-xl text-slate-500 shadow-sm" aria-hidden="true">+</span>
        <span class="mt-3 block text-[15px] font-medium text-slate-900">Ajouter une photo</span>
        <span class="mt-1 block text-sm text-slate-500">On affine l’estimation, et le prestataire sait quoi emporter.</span>
    </label>

    <div wire:loading wire:target="value" class="mt-3 text-sm text-slate-500">Envoi en cours…</div>
</div>
