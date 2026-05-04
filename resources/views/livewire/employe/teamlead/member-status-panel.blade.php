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
