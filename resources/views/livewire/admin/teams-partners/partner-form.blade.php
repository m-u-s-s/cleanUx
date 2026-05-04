<div class="rounded-2xl border border-slate-200 p-4 space-y-4">
    <h3 class="font-semibold text-slate-900">{{ $selectedPartner ? 'Modifier le partenaire' : 'Créer un partenaire' }}</h3>

    <div class="grid gap-4 md:grid-cols-2">
        <div><label class="text-sm font-medium text-slate-700">Nom</label><input wire:model.defer="partnerForm.name" type="text" class="mt-1 w-full rounded-xl border-slate-300" /></div>
        <div><label class="text-sm font-medium text-slate-700">Raison sociale</label><input wire:model.defer="partnerForm.legal_name" type="text" class="mt-1 w-full rounded-xl border-slate-300" /></div>
        <div>
            <label class="text-sm font-medium text-slate-700">Pays</label>
            <select wire:model.defer="partnerForm.country_id" class="mt-1 w-full rounded-xl border-slate-300">
                <option value="">—</option>
                @foreach($countries as $country)
                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Statut</label>
            <select wire:model.defer="partnerForm.status" class="mt-1 w-full rounded-xl border-slate-300">
                <option value="active">Active</option>
                <option value="pilot">Pilot</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div><label class="text-sm font-medium text-slate-700">Email</label><input wire:model.defer="partnerForm.email" type="email" class="mt-1 w-full rounded-xl border-slate-300" /></div>
        <div><label class="text-sm font-medium text-slate-700">Téléphone</label><input wire:model.defer="partnerForm.phone" type="text" class="mt-1 w-full rounded-xl border-slate-300" /></div>
        <div><label class="text-sm font-medium text-slate-700">Email facturation</label><input wire:model.defer="partnerForm.billing_email" type="email" class="mt-1 w-full rounded-xl border-slate-300" /></div>
        <div><label class="text-sm font-medium text-slate-700">Score qualité</label><input wire:model.defer="partnerForm.quality_score" type="number" step="0.01" min="0" max="100" class="mt-1 w-full rounded-xl border-slate-300" /></div>
    </div>

    <div class="flex items-center gap-2">
        <input wire:model.defer="partnerForm.is_active" id="partner-active" type="checkbox" class="rounded border-slate-300" />
        <label for="partner-active" class="text-sm text-slate-700">Partenaire actif</label>
    </div>

    <div>
        <label class="text-sm font-medium text-slate-700">Notes</label>
        <textarea wire:model.defer="partnerForm.notes" rows="3" class="mt-1 w-full rounded-xl border-slate-300"></textarea>
    </div>

    <button wire:click="savePartner" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
        Enregistrer le partenaire
    </button>
</div>
