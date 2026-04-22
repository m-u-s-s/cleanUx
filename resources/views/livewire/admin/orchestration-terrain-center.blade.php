<div class="space-y-6 p-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Orchestration terrain</h1>
        <p class="text-sm text-slate-600">Planifie des chantiers multi-jours, des lots de missions et des répartitions multi-équipes.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-slate-900">Créer un lot de missions</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Compte entreprise</label>
                    <select wire:model="organization_account_id" class="w-full rounded-xl border-slate-300">
                        <option value="">Sélectionner</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Site</label>
                    <select wire:model="organization_site_id" class="w-full rounded-xl border-slate-300">
                        <option value="">Sélectionner</option>
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Équipe terrain</label>
                    <select wire:model="field_team_id" class="w-full rounded-xl border-slate-300">
                        <option value="">Sélectionner</option>
                        @foreach($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Partenaire</label>
                    <select wire:model="service_partner_id" class="w-full rounded-xl border-slate-300">
                        <option value="">Sélectionner</option>
                        @foreach($partners as $partner)
                            <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nom du lot / chantier</label>
                    <input type="text" wire:model="name" class="w-full rounded-xl border-slate-300" placeholder="Ex: Chantier Bruxelles phase 1">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Début</label>
                    <input type="date" wire:model="starts_on" class="w-full rounded-xl border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Fin</label>
                    <input type="date" wire:model="ends_on" class="w-full rounded-xl border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                    <select wire:model="batch_type" class="w-full rounded-xl border-slate-300">
                        <option value="multi_day_site">Multi-jours site</option>
                        <option value="construction_phase">Phase chantier</option>
                        <option value="multi_team_office">Bureaux multi-équipes</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Segments / jour</label>
                    <input type="number" min="1" max="10" wire:model="segments_per_day" class="w-full rounded-xl border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Taille équipage</label>
                    <input type="number" min="1" max="20" wire:model="crew_size" class="w-full rounded-xl border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Minutes estimées / segment</label>
                    <input type="number" min="30" max="1440" wire:model="estimated_segment_minutes" class="w-full rounded-xl border-slate-300">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                    <textarea wire:model="notes" rows="3" class="w-full rounded-xl border-slate-300"></textarea>
                </div>
            </div>
            <div class="pt-2">
                <button wire:click="createBatch" class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-white font-semibold hover:bg-blue-700">Créer le lot</button>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h2 class="text-lg font-semibold text-slate-900 mb-4">Vue rapide</h2>
            <div class="space-y-3 text-sm text-slate-700">
                <div class="rounded-xl bg-slate-50 p-4">
                    <div class="font-semibold">Objectif</div>
                    <div>Orchestrer des missions multi-jours, multi-zones et multi-équipes avec visibilité terrain.</div>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <div class="font-semibold">Cas couverts</div>
                    <div>Chantier, bureau multisite, phase de remise à niveau, interventions groupées.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-lg font-semibold text-slate-900 mb-4">Lots récents</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2 pe-4">Référence</th>
                        <th class="py-2 pe-4">Nom</th>
                        <th class="py-2 pe-4">Compte</th>
                        <th class="py-2 pe-4">Période</th>
                        <th class="py-2 pe-4">Statut</th>
                        <th class="py-2 pe-4">Jours</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentBatches as $batch)
                        <tr class="border-b last:border-0">
                            <td class="py-2 pe-4 font-medium text-slate-900">{{ $batch->reference }}</td>
                            <td class="py-2 pe-4">{{ $batch->name }}</td>
                            <td class="py-2 pe-4">{{ $batch->organizationAccount->name ?? '—' }}</td>
                            <td class="py-2 pe-4">{{ optional($batch->starts_on)->format('d/m/Y') }} → {{ optional($batch->ends_on)->format('d/m/Y') }}</td>
                            <td class="py-2 pe-4">{{ $batch->status }}</td>
                            <td class="py-2 pe-4">{{ $batch->days->count() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500">Aucun lot de mission pour le moment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
