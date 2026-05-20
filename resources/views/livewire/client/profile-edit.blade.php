<div class="py-8 max-w-2xl mx-auto px-4 space-y-6">

    <div>
        <p class="text-sm font-bold uppercase text-indigo-600">Compte</p>
        <h1 class="text-2xl font-black text-slate-900">Mon profil</h1>
    </div>

    <div class="rounded-2xl bg-white border shadow-sm p-6">
        <h2 class="text-sm font-bold text-slate-900 mb-4">Informations personnelles</h2>
        <div class="space-y-3">
            <div>
                <label class="text-xs font-bold text-slate-600">Nom complet</label>
                <input wire:model="name" type="text" class="w-full rounded-lg border-slate-300 text-sm mt-1">
                @error('name') <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-bold text-slate-600">Email</label>
                <input wire:model="email" type="email" class="w-full rounded-lg border-slate-300 text-sm mt-1">
                @error('email') <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-bold text-slate-600">Téléphone</label>
                <input wire:model="phone" type="tel" class="w-full rounded-lg border-slate-300 text-sm mt-1">
                @error('phone') <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
            </div>
            <button wire:click="updateProfile" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-500">
                Enregistrer
            </button>
        </div>
    </div>

    <div class="rounded-2xl bg-white border shadow-sm p-6">
        <h2 class="text-sm font-bold text-slate-900 mb-4">Changer mon mot de passe</h2>
        <div class="space-y-3">
            <div>
                <label class="text-xs font-bold text-slate-600">Mot de passe actuel</label>
                <input wire:model="current_password" type="password" class="w-full rounded-lg border-slate-300 text-sm mt-1">
                @error('current_password') <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-bold text-slate-600">Nouveau mot de passe (min. 8 caractères)</label>
                <input wire:model="password" type="password" class="w-full rounded-lg border-slate-300 text-sm mt-1">
                @error('password') <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-bold text-slate-600">Confirmer le nouveau mot de passe</label>
                <input wire:model="password_confirmation" type="password" class="w-full rounded-lg border-slate-300 text-sm mt-1">
            </div>
            <button wire:click="updatePassword" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-500">
                Changer le mot de passe
            </button>
        </div>
    </div>

    <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-sm">
        <p class="font-semibold text-amber-900 mb-1">Supprimer mon compte</p>
        <p class="text-xs text-amber-700">La suppression de votre compte déclenche une demande RGPD avec période de grâce de 30 jours.</p>
        <a href="{{ route('client.gdpr') }}" class="inline-block mt-2 text-xs font-semibold text-amber-900 underline">Faire une demande RGPD →</a>
    </div>
</div>
