<div class="mt-10 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
        @if($step > 1 && $step < 5)
        <button type="button" wire:click="previousStep" class="rounded-2xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700">Retour</button>
        @endif
    </div>

    <div class="flex items-center gap-3">
        @if($step < 4)
        <button type="button" wire:click="nextStep" class="rounded-2xl bg-sky-600 px-5 py-3 text-sm font-semibold text-white">Continuer</button>
        @endif

        @if($step === 4)
        <button type="button" wire:click="nextStep" class="rounded-2xl bg-sky-600 px-5 py-3 text-sm font-semibold text-white">Voir le résumé</button>
        @endif

        @if($step === 5)
            @if($isGuestBooking)
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" wire:click="redirectToAuthentication('register')" wire:loading.attr="disabled" wire:target="redirectToAuthentication" class="rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-60 disabled:cursor-not-allowed">Créer mon compte et confirmer</button>
                <button type="button" wire:click="redirectToAuthentication('login')" wire:loading.attr="disabled" wire:target="redirectToAuthentication" class="rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 disabled:opacity-60 disabled:cursor-not-allowed">J’ai déjà un compte</button>
            </div>
            @else
            <button type="button" wire:click="validerRdv" wire:loading.attr="disabled" wire:target="validerRdv" class="rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="validerRdv">Confirmer ma demande</span>
                <span wire:loading wire:target="validerRdv">Envoi en cours...</span>
            </button>
            @endif
        @endif
    </div>
</div>
