@if($selectedPartner)
    <div class="rounded-2xl border border-slate-200 p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-slate-900">Couvertures & capacités</h3>
            <span class="text-xs text-slate-500">{{ $selectedPartner->zoneCoverages->count() }} couverture(s)</span>
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <div>
                <label class="text-sm font-medium text-slate-700">Zone</label>
                <select wire:model.defer="coverageForm.service_zone_id" class="mt-1 w-full rounded-xl border-slate-300">
                    <option value="">—</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Service</label>
                <select wire:model.defer="coverageForm.service_catalog_id" class="mt-1 w-full rounded-xl border-slate-300">
                    <option value="">Tous</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="text-sm font-medium text-slate-700">Capacité / jour</label><input wire:model.defer="coverageForm.max_daily_capacity" type="number" min="1" class="mt-1 w-full rounded-xl border-slate-300" /></div>
            <div><label class="text-sm font-medium text-slate-700">SLA réponse (h)</label><input wire:model.defer="coverageForm.sla_response_hours" type="number" min="1" class="mt-1 w-full rounded-xl border-slate-300" /></div>
        </div>

        <div class="flex items-center justify-between">
            <div class="w-32">
                <label class="text-sm font-medium text-slate-700">Priorité</label>
                <input wire:model.defer="coverageForm.priority" type="number" min="1" max="100" class="mt-1 w-full rounded-xl border-slate-300" />
            </div>
            <button wire:click="addCoverage" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                Ajouter la couverture
            </button>
        </div>

        <div class="space-y-2">
            @forelse($selectedPartner->zoneCoverages as $coverage)
                <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <div class="font-medium text-slate-900">{{ $coverage->serviceZone->name ?? 'Zone' }}</div>
                    <div class="mt-1 text-xs text-slate-500">
                        Service : {{ $coverage->serviceCatalog->name ?? 'Tous' }} · Priorité {{ $coverage->priority }} · Capacité {{ $coverage->max_daily_capacity ?? '—' }} · SLA {{ $coverage->sla_response_hours ?? '—' }}h
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">Aucune couverture configurée pour ce partenaire.</p>
            @endforelse
        </div>
    </div>
@endif
