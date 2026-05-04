<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
            Nouveau lot
        </p>
        <h2 class="mt-1 text-xl font-black text-slate-900">
            Créer un lot de mission
        </h2>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-bold text-slate-700">Compte</label>
            <select wire:model="organization_account_id" class="w-full rounded-xl border-slate-300 text-sm shadow-sm">
                <option value="">Sélectionner</option>
                @foreach($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-bold text-slate-700">Site</label>
            <select wire:model="organization_site_id" class="w-full rounded-xl border-slate-300 text-sm shadow-sm">
                <option value="">Sélectionner</option>
                @foreach($sites as $site)
                    <option value="{{ $site->id }}">{{ $site->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-bold text-slate-700">Équipe terrain</label>
            <select wire:model="field_team_id" class="w-full rounded-xl border-slate-300 text-sm shadow-sm">
                <option value="">Sélectionner</option>
                @foreach($teams as $team)
                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-bold text-slate-700">Partenaire</label>
            <select wire:model="service_partner_id" class="w-full rounded-xl border-slate-300 text-sm shadow-sm">
                <option value="">Sélectionner</option>
                @foreach($partners as $partner)
                    <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-sm font-bold text-slate-700">Nom du lot / chantier</label>
            <input
                type="text"
                wire:model="name"
                class="w-full rounded-xl border-slate-300 text-sm shadow-sm"
                placeholder="Ex: Chantier Bruxelles phase 1">
            @error('name')
                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-bold text-slate-700">Début</label>
            <input type="date" wire:model="starts_on" class="w-full rounded-xl border-slate-300 text-sm shadow-sm">
            @error('starts_on')
                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-bold text-slate-700">Fin</label>
            <input type="date" wire:model="ends_on" class="w-full rounded-xl border-slate-300 text-sm shadow-sm">
            @error('ends_on')
                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-bold text-slate-700">Type</label>
            <select wire:model="batch_type" class="w-full rounded-xl border-slate-300 text-sm shadow-sm">
                <option value="multi_day_site">Multi-jours site</option>
                <option value="construction_phase">Phase chantier</option>
                <option value="multi_team_office">Bureaux multi-équipes</option>
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-bold text-slate-700">Segments / jour</label>
            <input type="number" min="1" max="10" wire:model="segments_per_day" class="w-full rounded-xl border-slate-300 text-sm shadow-sm">
        </div>

        <div>
            <label class="mb-1 block text-sm font-bold text-slate-700">Taille équipage</label>
            <input type="number" min="1" max="20" wire:model="crew_size" class="w-full rounded-xl border-slate-300 text-sm shadow-sm">
        </div>

        <div>
            <label class="mb-1 block text-sm font-bold text-slate-700">Minutes estimées / segment</label>
            <input type="number" min="30" max="1440" wire:model="estimated_segment_minutes" class="w-full rounded-xl border-slate-300 text-sm shadow-sm">
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-sm font-bold text-slate-700">Notes</label>
            <textarea wire:model="notes" rows="3" class="w-full rounded-xl border-slate-300 text-sm shadow-sm"></textarea>
        </div>
    </div>

    <div class="pt-5">
        <button
            type="button"
            wire:click="createBatch"
            class="inline-flex items-center rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white transition hover:bg-indigo-700">
            Créer le lot
        </button>
    </div>
</section>
