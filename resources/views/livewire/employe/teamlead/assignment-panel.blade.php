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
