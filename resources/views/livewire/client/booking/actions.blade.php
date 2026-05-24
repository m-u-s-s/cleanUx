<div class="mt-10 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
        @if($step > 1 && $step < 5)
            <button type="button" wire:click="previousStep"
                    class="cu-btn-secondary inline-flex items-center gap-2">
                <x-ui.icon name="arrow-left" class="w-4 h-4" />
                <span>Retour</span>
            </button>
        @endif
    </div>

    <div class="flex items-center gap-2">
        @if($step < 4)
            <button type="button" wire:click="nextStep"
                    class="cu-btn-primary inline-flex items-center gap-2">
                <span>Continuer</span>
                <x-ui.icon name="arrow-right" class="w-4 h-4" />
            </button>
        @endif

        @if($step === 4)
            <button type="button" wire:click="nextStep"
                    class="cu-btn-primary inline-flex items-center gap-2">
                <span>Voir le résumé</span>
                <x-ui.icon name="arrow-right" class="w-4 h-4" />
            </button>
        @endif

        @if($step === 5)
            @if($isGuestBooking)
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="redirectToAuthentication('register')"
                            wire:loading.attr="disabled" wire:target="redirectToAuthentication"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
                        <x-ui.icon name="user" class="w-4 h-4" />
                        <span>Créer mon compte et confirmer</span>
                    </button>
                    <button type="button" wire:click="redirectToAuthentication('login')"
                            wire:loading.attr="disabled" wire:target="redirectToAuthentication"
                            class="cu-btn-secondary inline-flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span>J'ai déjà un compte</span>
                    </button>
                </div>
            @else
                <button type="button" wire:click="validerRdv"
                        wire:loading.attr="disabled" wire:target="validerRdv"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
                    <span wire:loading.remove wire:target="validerRdv" class="inline-flex items-center gap-2">
                        <x-ui.icon name="check-circle" class="w-4 h-4" />
                        Confirmer ma demande
                    </span>
                    <span wire:loading wire:target="validerRdv" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        Envoi en cours…
                    </span>
                </button>
            @endif
        @endif
    </div>
</div>
