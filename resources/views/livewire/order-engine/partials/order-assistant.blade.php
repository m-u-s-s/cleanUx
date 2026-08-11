{{--
    L'ASSISTANT DE COMMANDE (E5).

    Le parcours commence par « choisissez un secteur », puis « un métier ». C'est parfait quand on
    sait qu'il faut un plafonneur ; ça ne l'est pas quand on écrit « il y a une auréole marron au
    plafond de la salle de bain ». Le client abandonne à l'étape zéro, ou choisit le mauvais métier
    et découvre l'erreur quand le professionnel arrive.

    IL PROPOSE, IL NE COMMANDE PAS. Il sélectionne le métier et rend la main : questions, adresse et
    confirmation restent au client.
--}}
<section class="rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="assistant-titre">
    <h2 id="assistant-titre" class="text-lg font-semibold text-slate-900">
        Dites-nous ce dont vous avez besoin
    </h2>
    <p class="mt-0.5 text-sm text-slate-500">
        En quelques mots, comme vous le diriez au téléphone.
    </p>

    <label for="besoin-decrit" class="mt-4 block">
        <span class="sr-only">Décrivez votre besoin</span>
        <textarea
            id="besoin-decrit"
            wire:model="besoinDecrit"
            rows="2"
            placeholder="Une auréole marron est apparue au plafond de la salle de bain."
            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-[15px] text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
        ></textarea>
        @error('besoinDecrit')
        <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
        @enderror
    </label>

    <button
        type="button"
        wire:click="interpreterMonBesoin"
        wire:loading.attr="disabled"
        class="mt-3 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
    >
        <span wire:loading.remove wire:target="interpreterMonBesoin">Trouver le bon service</span>
        <span wire:loading wire:target="interpreterMonBesoin">Un instant…</span>
    </button>

    @if ($interpretation)
    <div class="mt-4 rounded-xl bg-slate-50 p-4">
        @if ($interpretation['trade_id'])
            <p class="text-sm text-slate-900">
                @if ($interpretation['confidence'] === 'low')
                    {{-- Confiance basse : on PROPOSE sans sélectionner. Embarquer le client sur un
                         métier deviné lui ferait remplir un questionnaire entier avant de
                         comprendre qu'il n'est pas au bon endroit. --}}
                    Il s'agit peut-être de <strong>{{ $interpretation['trade_name'] }}</strong>.
                @else
                    Nous avons retenu <strong>{{ $interpretation['trade_name'] }}</strong>.
                @endif
            </p>

            @if ($interpretation['summary'])
            <p class="mt-1 text-xs text-slate-500">« {{ $interpretation['summary'] }} »</p>
            @endif

            @if ($interpretation['confidence'] === 'low')
            <button
                type="button"
                wire:click="accepterLaProposition"
                class="mt-3 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-white"
            >
                Oui, c'est ça
            </button>
            @endif

            <p class="mt-3 text-xs text-slate-400">
                Vous gardez la main : rien n'est commandé tant que vous n'avez pas confirmé.
            </p>
        @else
            <p class="text-sm text-slate-700">{{ $interpretation['summary'] }}</p>
        @endif
    </div>
    @endif
</section>
