<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-semibold text-slate-700 mb-2">Matériel spécifique</label>
        <input type="text" wire:model.defer="materiel_specifique" class="w-full rounded-2xl border-slate-300" placeholder="Ex. produits hypoallergéniques, aspirateur spécial...">
    </div>

    <div class="rounded-2xl border border-slate-200 p-4 space-y-3 bg-slate-50/60">
        <label class="flex items-center gap-3"><input type="checkbox" wire:model.live="presence_animaux"><span class="text-sm text-slate-700">Présence d’animaux</span></label>
        <label class="flex items-center gap-3"><input type="checkbox" wire:model.live="acces_parking"><span class="text-sm text-slate-700">Parking / accès facile</span></label>
        <label class="flex items-center gap-3"><input type="checkbox" wire:model.live="materiel_fournit"><span class="text-sm text-slate-700">Matériel fourni sur place</span></label>
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-semibold text-slate-700 mb-2">Photos de référence</label>
        <input type="file" wire:model="photos" multiple class="w-full rounded-2xl border-slate-300">
        @error('photos.*') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
    </div>
</div>
