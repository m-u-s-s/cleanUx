<div class="rounded-2xl border border-slate-200 p-4 space-y-4">
    <h3 class="font-semibold text-slate-900">{{ $selectedTeam ? 'Modifier l’équipe' : 'Créer une équipe' }}</h3>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="text-sm font-medium text-slate-700" for="teamForm-name">Nom</label>
            <input id="teamForm-name" wire:model.defer="teamForm.name" type="text" class="mt-1 w-full rounded-xl border-slate-300" />
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700" for="teamForm-country_id">Pays</label>
            <select id="teamForm-country_id" wire:model.defer="teamForm.country_id" class="mt-1 w-full rounded-xl border-slate-300">
                <option value="">—</option>
                @foreach($countries as $country)
                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700" for="teamForm-service_zone_id">Zone</label>
            <select id="teamForm-service_zone_id" wire:model.defer="teamForm.service_zone_id" class="mt-1 w-full rounded-xl border-slate-300">
                <option value="">—</option>
                @foreach($zones as $zone)
                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700" for="teamForm-organization_account_id">Compte entreprise</label>
            <select id="teamForm-organization_account_id" wire:model.defer="teamForm.organization_account_id" class="mt-1 w-full rounded-xl border-slate-300">
                <option value="">—</option>
                @foreach($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700" for="teamForm-service_partner_id">Partenaire</label>
            <select id="teamForm-service_partner_id" wire:model.defer="teamForm.service_partner_id" class="mt-1 w-full rounded-xl border-slate-300">
                <option value="">—</option>
                @foreach($partners as $partner)
                    <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700" for="teamForm-team_lead_user_id">Chef d’équipe</label>
            <select id="teamForm-team_lead_user_id" wire:model.defer="teamForm.team_lead_user_id" class="mt-1 w-full rounded-xl border-slate-300">
                <option value="">—</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700" for="teamForm-status">Statut</label>
            <select id="teamForm-status" wire:model.defer="teamForm.status" class="mt-1 w-full rounded-xl border-slate-300">
                <option value="active">Active</option>
                <option value="pilot">Pilot</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700" for="teamForm-max_concurrent_missions">Capacité max missions simultanées</label>
            <input id="teamForm-max_concurrent_missions" wire:model.defer="teamForm.max_concurrent_missions" type="number" min="1" class="mt-1 w-full rounded-xl border-slate-300" />
        </div>
    </div>

    <div class="flex items-center gap-2">
        <input wire:model.defer="teamForm.is_internal" id="team-internal" type="checkbox" class="rounded border-slate-300" />
        <label for="team-internal" class="text-sm text-slate-700">Équipe interne</label>
    </div>

    <div>
        <label class="text-sm font-medium text-slate-700" for="teamForm-notes">Notes</label>
        <textarea id="teamForm-notes" wire:model.defer="teamForm.notes" rows="3" class="mt-1 w-full rounded-xl border-slate-300"></textarea>
    </div>

    <button wire:click="saveTeam" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
        Enregistrer l’équipe
    </button>
</div>
