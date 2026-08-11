<div class="mx-auto max-w-2xl px-4 py-8">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Sécurité</h1>
        <p class="mt-1 text-sm text-slate-500">
            Si quelque chose ne va pas, dites-le. Nous saurons où vous êtes.
        </p>
    </header>

    @if ($refus)
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        {{ $refus }}
    </div>
    @endif

    @if ($alerte)
    <div class="mb-8 rounded-2xl border border-rose-200 bg-rose-50 p-5" data-test="alerte-en-cours">
        <p class="text-sm font-bold text-rose-800">
            {{ $alerte->level === \App\Models\SafetyAlert::LEVEL_EMERGENCY ? 'Urgence en cours' : 'Veille en cours' }}
        </p>

        <p class="mt-2 text-sm text-slate-800">
            @if ($alerte->acknowledged_at)
            {{-- Ce que la personne sur place attend de savoir en premier. --}}
            Un membre de l'équipe sécurité suit votre situation.
            @else
            Alerte transmise. Nous cherchons quelqu'un pour la prendre en charge.
            @endif
        </p>

        @if ($alerte->contact_notified_at)
        <p class="mt-1 text-xs text-slate-600">Votre contact d'urgence a été prévenu.</p>
        @endif

        <button type="button" wire:click="fermer({{ $alerte->id }})"
            class="mt-4 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            Tout va bien, fermer l'alerte
        </button>
    </div>
    @else
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5">
        <label for="sos-message" class="block">
            <span class="mb-1 block text-sm font-semibold text-slate-900">Que se passe-t-il ?</span>
            <input id="sos-message" type="text" wire:model="message" placeholder="Facultatif"
                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-[15px] text-slate-900">
        </label>

        {{--
            AUCUNE CONFIRMATION AVANT DE DÉCLENCHER. Une boîte de dialogue « êtes-vous sûr ? »
            ajoute un geste au moment où les mains tremblent : une alerte de trop coûte une
            vérification, une alerte manquante coûte autre chose.
        --}}
        <button type="button" wire:click="declencher('emergency')"
            class="mt-4 w-full rounded-xl bg-rose-600 px-4 py-3 text-base font-bold text-white transition hover:bg-rose-700">
            URGENCE — envoyer l'alerte
        </button>

        <button type="button" wire:click="declencher('check_in')"
            class="mt-3 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            Je ne suis pas à l'aise — gardez un œil
        </button>

        <p class="mt-4 text-xs text-slate-500">
            En cas de danger immédiat, appelez d'abord les secours. Cette alerte prévient l'équipe de
            la plateforme et, en cas d'urgence, votre contact d'urgence.
        </p>
    </div>
    @endif

    {{-- Le contact d'urgence, renseigné À FROID --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="mb-1 text-sm font-bold uppercase tracking-wide text-slate-500">Contact d'urgence</h2>
        <p class="mb-4 text-xs text-slate-500">
            Renseigné à l'avance. Le demander au moment du déclenchement reviendrait à ne l'avoir
            jamais.
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            <label for="contact-nom" class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Nom</span>
                <input id="contact-nom" type="text" wire:model="contactNom"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('contactNom')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label for="contact-telephone" class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Téléphone</span>
                <input id="contact-telephone" type="tel" wire:model="contactTelephone"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('contactTelephone')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>
        </div>

        <button type="button" wire:click="enregistrerLeContact"
            class="mt-4 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            Enregistrer
        </button>
    </div>
</div>
