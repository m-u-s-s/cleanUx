<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-blue-900">🧭 Chef d’équipe opérationnel</h2>
                <p class="text-sm text-gray-500">Répartition des segments, suivi par membre, renfort et clôture globale.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-6">
            <div class="bg-white rounded-2xl border shadow-sm p-4 space-y-4">
                <div>
                    <label class="text-sm font-semibold text-slate-700">Lot / chantier</label>
                    <select wire:model.live="selectedBatchId" class="mt-1 w-full rounded-xl border-slate-300">
                        <option value="">Choisir un lot</option>
                        @foreach($batches as $batch)
                            <option value="{{ $batch->id }}">#{{ $batch->id }} — {{ $batch->name ?? $batch->batch_type }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Segment</label>
                    <select wire:model.live="selectedSegmentId" class="mt-1 w-full rounded-xl border-slate-300">
                        <option value="">Choisir un segment</option>
                        @foreach($segments as $segment)
                            <option value="{{ $segment->id }}">{{ $segment->segment_label ?? ('Segment #'.$segment->id) }}</option>
                        @endforeach
                    </select>
                </div>

                @if($selectedSegment)
                    <div class="rounded-xl border bg-slate-50 p-3 text-sm">
                        <p class="font-semibold text-slate-800">{{ $selectedSegment->segment_label ?? ('Segment #'.$selectedSegment->id) }}</p>
                        <p class="text-slate-500">{{ $selectedSegment->segment_date }} · {{ $selectedSegment->estimated_minutes }} min</p>
                        <p class="text-slate-500">Mission #{{ $selectedSegment->mission_id ?? '—' }}</p>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                @if($selectedSegment)
                    <div class="bg-white rounded-2xl border shadow-sm p-5 space-y-4">
                        <h3 class="text-lg font-bold text-slate-900">Affectation fine des segments</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-semibold text-slate-700">Membre</label>
                                <select wire:model="selectedAssigneeId" class="mt-1 w-full rounded-xl border-slate-300">
                                    <option value="">Choisir un membre</option>
                                    @foreach(($selectedSegment->fieldTeam->members ?? collect()) as $member)
                                        <option value="{{ $member->user_id }}">{{ $member->user->name ?? ('User #'.$member->user_id) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button wire:click="assignSelectedSegment" class="w-full inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition">
                                    Affecter le segment
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border shadow-sm p-5 space-y-4">
                        <h3 class="text-lg font-bold text-slate-900">Statut membre par membre</h3>
                        <div class="space-y-3">
                            @forelse($selectedSegment->assignments as $assignment)
                                <div class="rounded-xl border p-4 space-y-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="font-semibold text-slate-900">{{ $assignment->user->name ?? ('User #'.$assignment->user_id) }}</p>
                                            <p class="text-sm text-slate-500">{{ $assignment->assignment_role }} · {{ $assignment->status }}</p>
                                        </div>
                                        <button wire:click="updateSelectedMemberStatus({{ $assignment->id }})" class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 transition">
                                            Mettre à jour
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <input wire:model="progressPercent" type="number" min="0" max="100" class="rounded-xl border-slate-300" placeholder="Progression %">
                                        <input wire:model="minutesSpent" type="number" min="0" class="rounded-xl border-slate-300" placeholder="Minutes passées">
                                        <input wire:model="blockingReason" type="text" class="rounded-xl border-slate-300" placeholder="Blocage éventuel">
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-slate-500">Aucune affectation membre sur ce segment.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border shadow-sm p-5 space-y-4">
                        <h3 class="text-lg font-bold text-slate-900">Demande de renfort</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <input wire:model="requestedMembers" type="number" min="1" class="rounded-xl border-slate-300" placeholder="Membres demandés">
                            <input wire:model="requestedMinutes" type="number" min="15" class="rounded-xl border-slate-300" placeholder="Minutes estimées">
                            <select wire:model="reinforcementPriority" class="rounded-xl border-slate-300">
                                <option value="normale">Normale</option>
                                <option value="haute">Haute</option>
                                <option value="urgente">Urgente</option>
                            </select>
                        </div>
                        <textarea wire:model="reinforcementReason" rows="3" class="w-full rounded-xl border-slate-300" placeholder="Pourquoi un renfort est nécessaire ?"></textarea>
                        <button wire:click="requestReinforcement" class="inline-flex items-center rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-600 transition">
                            Envoyer la demande de renfort
                        </button>
                    </div>
                @else
                    <div class="bg-white rounded-2xl border border-dashed p-8 text-center text-slate-500">
                        Sélectionnez un lot puis un segment pour ouvrir le cockpit chef d’équipe.
                    </div>
                @endif

                <div class="bg-white rounded-2xl border shadow-sm p-5 space-y-4">
                    <h3 class="text-lg font-bold text-slate-900">Demandes de renfort récentes</h3>
                    <div class="space-y-3">
                        @forelse($reinforcementRequests as $request)
                            <div class="rounded-xl border p-4">
                                <p class="font-semibold text-slate-900">{{ $request->priority }} · {{ $request->status }}</p>
                                <p class="text-sm text-slate-500">{{ $request->reason }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Aucune demande récente.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
