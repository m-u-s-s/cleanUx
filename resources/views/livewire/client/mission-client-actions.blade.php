<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
    <h3 class="text-lg font-semibold text-slate-900">Actions client</h3>

    @if($successMessage)
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ $successMessage }}
    </div>
    @endif

    <div class="grid gap-3 md:grid-cols-2">
        <button
            wire:click="confirmPresence"
            type="button"
            class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white">
            Valider présence
        </button>

        <div class="space-y-2">
            <textarea
                wire:model.defer="issueMessage"
                rows="3"
                placeholder="Décrivez le problème..."
                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:outline-none"></textarea>

            <button
                wire:click="reportIssue"
                type="button"
                class="rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white">
                Signaler un problème
            </button>

            @error('issueMessage')
            <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>
    </div>