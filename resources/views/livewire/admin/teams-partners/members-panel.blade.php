@if($selectedTeam)
    <div class="rounded-2xl border border-slate-200 p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-slate-900">Membres de {{ $selectedTeam->name }}</h3>
            <span class="text-xs text-slate-500">{{ $selectedTeam->activeMembers->count() }} actif(s)</span>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            <div>
                <label class="text-sm font-medium text-slate-700">Employé</label>
                <select wire:model.defer="memberForm.user_id" class="mt-1 w-full rounded-xl border-slate-300">
                    <option value="">—</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Rôle</label>
                <select wire:model.defer="memberForm.role_on_team" class="mt-1 w-full rounded-xl border-slate-300">
                    <option value="team_lead">Chef d’équipe</option>
                    <option value="senior_agent">Senior agent</option>
                    <option value="agent">Agent</option>
                    <option value="specialist">Spécialiste</option>
                    <option value="driver">Chauffeur</option>
                </select>
            </div>
            <div class="flex items-end gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input wire:model.defer="memberForm.is_team_lead" type="checkbox" class="rounded border-slate-300" />
                    Lead
                </label>
                <button wire:click="addMember" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                    Ajouter
                </button>
            </div>
        </div>

        <div class="space-y-2">
            @forelse($selectedTeam->activeMembers as $member)
                <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm">
                    <div>
                        <p class="font-medium text-slate-900">{{ $member->user->name }}</p>
                        <p class="text-slate-500">{{ $member->role_on_team }}</p>
                    </div>
                    @if($member->is_team_lead)
                        <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">Lead</span>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">Aucun membre actif pour cette équipe.</p>
            @endforelse
        </div>
    </div>
@endif
