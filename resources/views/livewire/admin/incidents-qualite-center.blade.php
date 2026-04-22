<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Incidents, litiges & qualité</h1>
            <p class="text-sm text-gray-500">Signalements terrain, réclamations clients, SLA léger et audits qualité.</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-2xl border bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Incidents ouverts</div><div class="mt-2 text-2xl font-bold text-slate-900">{{ $kpis['incidents_open'] }}</div></div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Litiges ouverts</div><div class="mt-2 text-2xl font-bold text-slate-900">{{ $kpis['complaints_open'] }}</div></div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Critiques</div><div class="mt-2 text-2xl font-bold text-rose-700">{{ $kpis['critical_open'] }}</div></div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">SLA dépassés</div><div class="mt-2 text-2xl font-bold text-amber-700">{{ $kpis['sla_overdue'] }}</div></div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Qualité moyenne</div><div class="mt-2 text-2xl font-bold text-emerald-700">{{ $kpis['quality_avg'] ?: '—' }}</div></div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm"><div class="text-xs uppercase text-gray-500">Suivis requis</div><div class="mt-2 text-2xl font-bold text-blue-700">{{ $kpis['follow_up_count'] }}</div></div>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="rounded-2xl border bg-white p-4 shadow-sm space-y-4">
        <div class="flex flex-wrap gap-2">
            <button wire:click="$set('tab','incidents')" class="rounded-xl px-4 py-2 text-sm {{ $tab === 'incidents' ? 'bg-slate-900 text-white' : 'border' }}">Incidents</button>
            <button wire:click="$set('tab','complaints')" class="rounded-xl px-4 py-2 text-sm {{ $tab === 'complaints' ? 'bg-slate-900 text-white' : 'border' }}">Litiges</button>
            <button wire:click="$set('tab','quality')" class="rounded-xl px-4 py-2 text-sm {{ $tab === 'quality' ? 'bg-slate-900 text-white' : 'border' }}">Qualité</button>
        </div>

        <div class="grid gap-3 lg:grid-cols-4">
            <input wire:model.live.debounce.300ms="search" type="text" class="rounded-xl border-gray-300 text-sm" placeholder="Recherche">
            <input wire:model.live="category" type="text" class="rounded-xl border-gray-300 text-sm" placeholder="Catégorie / type">
            <select wire:model.live="priority" class="rounded-xl border-gray-300 text-sm">
                <option value="">Toutes priorités</option>
                <option value="faible">Faible</option>
                <option value="normale">Normale</option>
                <option value="haute">Haute</option>
                <option value="critique">Critique</option>
            </select>
            <select wire:model.live="status" class="rounded-xl border-gray-300 text-sm">
                <option value="">Tous statuts</option>
                <option value="ouvert">Ouvert</option>
                <option value="en_cours">En cours</option>
                <option value="en_attente_client">En attente client</option>
                <option value="resolu">Résolu</option>
                <option value="ferme">Fermé</option>
                <option value="publie">Publié</option>
                <option value="brouillon">Brouillon</option>
            </select>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr),minmax(320px,1fr)]">
        <div class="space-y-6">
            <div class="overflow-hidden rounded-2xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600"><tr><th class="px-4 py-3">Sujet</th><th class="px-4 py-3">Statut</th><th class="px-4 py-3">Priorité / SLA</th><th class="px-4 py-3">Référence</th><th class="px-4 py-3">Porteur</th></tr></thead>
                        <tbody class="divide-y">
                        @forelse($rows as $row)
                            <tr wire:click="selectRow({{ $row->id }})" class="cursor-pointer hover:bg-slate-50 {{ $selectedRow && $selectedRow->id === $row->id ? 'bg-blue-50' : '' }}">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">{{ $tab === 'complaints' ? $row->subject : ($tab === 'quality' ? 'Audit #'.$row->id : $row->title) }}</div>
                                    <div class="text-xs text-gray-500">{{ $tab === 'quality' ? ($row->serviceZone?->name ?? 'Zone non définie') : ($tab === 'complaints' ? $row->category : $row->type) }}</div>
                                </td>
                                <td class="px-4 py-3"><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold">{{ $row->status }}</span></td>
                                <td class="px-4 py-3">
                                    <div>{{ $tab === 'quality' ? ($row->follow_up_required ? 'Suivi requis' : 'RAS') : $row->priority }}</div>
                                    @if($tab !== 'quality')<div class="text-xs text-gray-500">{{ $row->sla_policy ?: '—' }} @if($row->due_at)• {{ $row->due_at->format('d/m H:i') }}@endif</div>@endif
                                </td>
                                <td class="px-4 py-3">{{ $row->rendezVous?->booking_reference ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $tab === 'complaints' ? ($row->client?->name ?? 'Client') : ($tab === 'quality' ? ($row->employe?->name ?? 'Employé') : ($row->employe?->name ?? $row->client?->name ?? '—')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Aucune donnée.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t bg-gray-50">{{ $rows->links() }}</div>
            </div>

            @if($tab === 'quality')
                <div class="rounded-2xl border bg-white p-4 shadow-sm space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">Créer un audit qualité</h2>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <select wire:model="auditRendezVousId" class="rounded-xl border-gray-300 text-sm"><option value="">Mission liée (optionnel)</option>@foreach($this->auditableRendezVous as $rdv)<option value="{{ $rdv->id }}">{{ $rdv->booking_reference }} — {{ $rdv->client?->name }} — {{ $rdv->serviceZone?->name }}</option>@endforeach</select>
                        <select wire:model="auditEmployeId" class="rounded-xl border-gray-300 text-sm"><option value="">Employé</option>@foreach($this->employes as $employe)<option value="{{ $employe->id }}">{{ $employe->name }}</option>@endforeach</select>
                        <select wire:model="auditZoneId" class="rounded-xl border-gray-300 text-sm"><option value="">Zone</option>@foreach($this->zones as $zone)<option value="{{ $zone->id }}">{{ $zone->name }}</option>@endforeach</select>
                        <select wire:model="auditStatus" class="rounded-xl border-gray-300 text-sm"><option value="publie">Publié</option><option value="brouillon">Brouillon</option><option value="action_required">Action requise</option></select>
                        <select wire:model="auditPunctuality" class="rounded-xl border-gray-300 text-sm">@for($i=1;$i<=5;$i++)<option value="{{ $i }}">Ponctualité {{ $i }}/5</option>@endfor</select>
                        <select wire:model="auditService" class="rounded-xl border-gray-300 text-sm">@for($i=1;$i<=5;$i++)<option value="{{ $i }}">Service {{ $i }}/5</option>@endfor</select>
                        <select wire:model="auditCommunication" class="rounded-xl border-gray-300 text-sm">@for($i=1;$i<=5;$i++)<option value="{{ $i }}">Communication {{ $i }}/5</option>@endfor</select>
                        <label class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm"><input type="checkbox" wire:model="auditFollowUp"> Suivi requis</label>
                    </div>
                    <textarea wire:model="auditNotes" rows="3" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Notes d'audit"></textarea>
                    <textarea wire:model="auditActionPlan" rows="3" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Plan d'action"></textarea>
                    <textarea wire:model="auditAttachmentInput" rows="3" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Preuves (1 lien/chemin par ligne)"></textarea>
                    <button wire:click="createAudit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Créer l'audit</button>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Détail</h2>
                @if($selectedRow)
                    <div class="mt-4 space-y-3 text-sm">
                        <div><span class="font-semibold">Référence :</span> {{ $selectedRow->rendezVous?->booking_reference ?? '—' }}</div>
                        <div><span class="font-semibold">Statut :</span> {{ $selectedRow->status }}</div>
                        @if($tab !== 'quality')
                            <div><span class="font-semibold">SLA :</span> {{ $selectedRow->sla_policy ?: '—' }} @if($selectedRow->due_at)• {{ $selectedRow->due_at->format('d/m/Y H:i') }}@endif @if($selectedRow->is_overdue)<span class="ml-2 rounded-full bg-red-100 px-2 py-1 text-xs text-red-700">en retard</span>@endif</div>
                        @endif
                        @if($tab === 'complaints')
                            <div><span class="font-semibold">Sujet :</span> {{ $selectedRow->subject }}</div>
                            <div><span class="font-semibold">Client :</span> {{ $selectedRow->client?->name }}</div>
                            <div><span class="font-semibold">Description :</span><br>{{ $selectedRow->description }}</div>
                            @if(!empty($selectedRow->attachments))<div><span class="font-semibold">Pièces jointes :</span><ul class="mt-1 list-disc list-inside">@foreach($selectedRow->attachments as $attachment)<li>{{ data_get($attachment, 'path') }}</li>@endforeach</ul></div>@endif
                            <select wire:model="assignedTo" class="w-full rounded-xl border-gray-300 text-sm"><option value="">Assigner à</option>@foreach($this->managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach</select>
                            <input wire:model="resolutionCategory" type="text" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Catégorie de résolution">
                            <textarea wire:model="complaintResponse" rows="4" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Réponse admin"></textarea>
                            <div class="flex flex-wrap gap-2">
                                <button wire:click="assignSelected" class="rounded-xl border px-3 py-2 text-sm">Assigner</button>
                                <button wire:click="saveComplaintResponse" class="rounded-xl bg-blue-600 px-3 py-2 text-sm text-white">Enregistrer réponse</button>
                                <button wire:click="updateSelectedStatus('en_cours')" class="rounded-xl border px-3 py-2 text-sm">En cours</button>
                                <button wire:click="updateSelectedStatus('en_attente_client')" class="rounded-xl border px-3 py-2 text-sm">Attente client</button>
                                <button wire:click="updateSelectedStatus('resolu')" class="rounded-xl border px-3 py-2 text-sm">Résolu</button>
                                <button wire:click="updateSelectedStatus('ferme')" class="rounded-xl border px-3 py-2 text-sm">Fermer</button>
                            </div>
                        @elseif($tab === 'quality')
                            <div><span class="font-semibold">Score :</span> {{ $selectedRow->score }}/5</div>
                            <div><span class="font-semibold">Employé :</span> {{ $selectedRow->employe?->name ?? '—' }}</div>
                            <div><span class="font-semibold">Zone :</span> {{ $selectedRow->serviceZone?->name ?? '—' }}</div>
                            <div><span class="font-semibold">Notes :</span><br>{{ $selectedRow->notes ?: '—' }}</div>
                            <div><span class="font-semibold">Plan d'action :</span><br>{{ $selectedRow->action_plan ?: '—' }}</div>
                            <div><span class="font-semibold">Suivi :</span> {{ $selectedRow->follow_up_required ? 'Oui' : 'Non' }} @if($selectedRow->follow_up_due_at)• {{ $selectedRow->follow_up_due_at->format('d/m/Y') }}@endif</div>
                        @else
                            <div><span class="font-semibold">Sujet :</span> {{ $selectedRow->title }}</div>
                            <div><span class="font-semibold">Déclarant :</span> {{ $selectedRow->employe?->name ?? $selectedRow->client?->name ?? '—' }}</div>
                            <div><span class="font-semibold">Description :</span><br>{{ $selectedRow->description ?: '—' }}</div>
                            <div><span class="font-semibold">Localisation :</span> {{ $selectedRow->location_notes ?: '—' }}</div>
                            @if(!empty($selectedRow->attachments))<div><span class="font-semibold">Preuves :</span><ul class="mt-1 list-disc list-inside">@foreach($selectedRow->attachments as $attachment)<li>{{ data_get($attachment, 'path') }}</li>@endforeach</ul></div>@endif
                            <select wire:model="assignedTo" class="w-full rounded-xl border-gray-300 text-sm"><option value="">Assigner à</option>@foreach($this->managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach</select>
                            <textarea wire:model="resolutionNotes" rows="4" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Notes de résolution"></textarea>
                            <div class="flex flex-wrap gap-2">
                                <button wire:click="assignSelected" class="rounded-xl border px-3 py-2 text-sm">Assigner</button>
                                <button wire:click="saveIncidentResolution" class="rounded-xl bg-blue-600 px-3 py-2 text-sm text-white">Enregistrer</button>
                                <button wire:click="updateSelectedStatus('en_cours')" class="rounded-xl border px-3 py-2 text-sm">En cours</button>
                                <button wire:click="updateSelectedStatus('en_attente_client')" class="rounded-xl border px-3 py-2 text-sm">Attente client</button>
                                <button wire:click="updateSelectedStatus('resolu')" class="rounded-xl border px-3 py-2 text-sm">Résolu</button>
                                <button wire:click="updateSelectedStatus('ferme')" class="rounded-xl border px-3 py-2 text-sm">Fermer</button>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="mt-3 text-sm text-gray-500">Sélectionne une ligne pour voir le détail.</p>
                @endif
            </div>
        </div>
    </div>
</div>
