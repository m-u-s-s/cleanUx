<div class="max-w-md mx-auto mt-12 p-6">
    <h1 class="text-2xl font-bold text-slate-900">Vérification du téléphone</h1>
    <p class="mt-1 text-sm text-slate-500">
        Confirmez votre numéro de mobile : nous vous envoyons un code à 6 chiffres par SMS.
    </p>

    @if (! $codeSent)
        <form wire:submit="sendCode" class="mt-6 space-y-4">
            <div>
                <label class="ui-label">Numéro de mobile</label>
                <input wire:model="phone" type="tel" inputmode="tel" placeholder="+32470000000" class="ui-input" autofocus>
                @error('phone') <p class="ui-error-msg">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="ui-btn-primary w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="sendCode">Recevoir le code</span>
                <span wire:loading wire:target="sendCode">Envoi…</span>
            </button>
        </form>
    @else
        <form wire:submit="verifyCode" class="mt-6 space-y-4">
            <div>
                <label class="ui-label">Code reçu par SMS</label>
                <input wire:model="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                       maxlength="8" placeholder="123456" class="ui-input tracking-widest text-center text-lg" autofocus>
                @error('code') <p class="ui-error-msg">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="ui-btn-primary w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="verifyCode">Vérifier</span>
                <span wire:loading wire:target="verifyCode">Vérification…</span>
            </button>
            <button type="button" wire:click="sendCode" class="ui-btn-ghost w-full text-sm">
                Renvoyer un code
            </button>
        </form>
    @endif
</div>
