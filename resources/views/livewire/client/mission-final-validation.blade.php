<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
    <h3 class="text-lg font-semibold text-slate-900">Validation finale</h3>

    @if($successMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ $successMessage }}
        </div>
    @endif

    <textarea
        wire:model.defer="comment"
        rows="4"
        placeholder="Commentaire final..."
        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:outline-none"
    ></textarea>

    @error('comment')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror

    <div class="grid gap-3 md:grid-cols-2">
        <button wire:click="satisfied" type="button" class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white">
            Satisfait
        </button>

        <button wire:click="problem" type="button" class="rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white">
            Problème
        </button>
    </div>
</div>