<div class="py-8 max-w-2xl mx-auto px-4 space-y-6">

    <div>
        <p class="ui-page-eyebrow">Compte</p>
        <h1 class="ui-page-title">Mon profil</h1>
        <p class="ui-page-subtitle">Gérez vos informations et la sécurité de votre compte.</p>
    </div>

    {{-- Informations personnelles --}}
    <div class="rounded-2xl bg-white border border-slate-200/80 shadow-soft p-6 dark:bg-slate-900 dark:border-slate-700">
        <div class="flex items-center gap-2 mb-4">
            <x-ui.icon name="user" class="w-4 h-4 text-slate-500 dark:text-slate-400" />
            <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Informations personnelles</h2>
        </div>

        <div class="space-y-4">
            <div>
                <label class="ui-label" for="name">Nom complet</label>
                <input id="name" wire:model="name" type="text" class="ui-input" autocomplete="name">
                @error('name') <p class="ui-error-msg">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="ui-label" for="email">Email</label>
                <input id="email" wire:model="email" type="email" class="ui-input" autocomplete="email">
                @error('email') <p class="ui-error-msg">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="ui-label" for="phone">Téléphone</label>
                <input id="phone" wire:model="phone" type="tel" class="ui-input" autocomplete="tel">
                @error('phone') <p class="ui-error-msg">{{ $message }}</p> @enderror
            </div>

            <button wire:click="updateProfile" class="brio-btn-primary inline-flex items-center gap-2">
                <x-ui.icon name="check" class="w-4 h-4" />
                <span>Enregistrer</span>
            </button>
        </div>
    </div>

    {{-- Mot de passe --}}
    <div class="rounded-2xl bg-white border border-slate-200/80 shadow-soft p-6 dark:bg-slate-900 dark:border-slate-700">
        <div class="flex items-center gap-2 mb-4">
            <x-ui.icon name="lock-closed" class="w-4 h-4 text-slate-500 dark:text-slate-400" />
            <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Changer mon mot de passe</h2>
        </div>

        <div class="space-y-4">
            <div>
                <label class="ui-label" for="current_password">Mot de passe actuel</label>
                <input id="current_password" wire:model="current_password" type="password" class="ui-input" autocomplete="current-password">
                @error('current_password') <p class="ui-error-msg">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="ui-label" for="password">Nouveau mot de passe</label>
                <input id="password" wire:model="password" type="password" class="ui-input" autocomplete="new-password">
                <p class="text-xs text-slate-500 mt-1">Minimum 8 caractères</p>
                @error('password') <p class="ui-error-msg">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="ui-label" for="password_confirmation">Confirmer le nouveau mot de passe</label>
                <input id="password_confirmation" wire:model="password_confirmation" type="password" class="ui-input" autocomplete="new-password">
            </div>

            <button wire:click="updatePassword" class="brio-btn-primary inline-flex items-center gap-2">
                <x-ui.icon name="key" class="w-4 h-4" />
                <span>Changer le mot de passe</span>
            </button>
        </div>
    </div>

    {{-- Zone danger --}}
    <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5">
        <div class="flex items-start gap-3">
            <div class="grid h-9 w-9 place-items-center rounded-xl bg-amber-100 text-amber-700 shrink-0">
                <x-ui.icon name="exclamation-triangle" class="w-4 h-4" />
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-amber-900">Supprimer mon compte</p>
                <p class="mt-1 text-xs text-amber-700">
                    La suppression déclenche une demande RGPD avec période de grâce de 30 jours.
                </p>
                <a href="{{ Route::has('client.gdpr.data') ? route('client.gdpr.data') : '#' }}"
                   class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-amber-900 hover:text-amber-950 underline">
                    Faire une demande RGPD
                    <x-ui.icon name="arrow-right" class="w-3 h-3" />
                </a>
            </div>
        </div>
    </div>
</div>
