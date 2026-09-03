{{--
    LE SIÈGE DE LA PLATEFORME.

    Une page solennelle et courte : qui le détient, ce qui le protège, et le seul geste qui le
    déplace. Elle ne ressemble volontairement à aucun autre écran d'administration — on n'y vient
    pas par hasard, et on ne doit pas s'y tromper de bouton.

    Elle dit AUSSI ce qu'elle ne protège pas. Une page de sécurité qui laisse croire à une
    protection absolue est plus dangereuse que pas de page du tout.
--}}
<div class="space-y-6">

    <x-page-shell
        :eyebrow="__('Propriété de la plateforme')"
        :title="__('Le siège de super-administrateur')"
        :subtitle="__('Il n’y en a qu’un, et il se transfère — il ne se duplique jamais.')" />

    @if($message)
        <div class="brio-alerte brio-alerte-success !mb-0"><span aria-hidden="true">✅</span><span>{{ $message }}</span></div>
    @endif

    @if($erreur)
        <div class="brio-alerte brio-alerte-danger !mb-0"><span aria-hidden="true">⚠️</span><span>{{ $erreur }}</span></div>
    @endif

    {{-- LE TITULAIRE, ET CE QUI LE PROTÈGE --}}
    <x-app-card>
        @php
            $titulaire = $this->titulaire;
            $aUnePhrase = ! empty($titulaire?->seat_secret_hash);
            $aUnSecondFacteur = ! empty($titulaire?->two_factor_secret);
        @endphp

        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="min-w-0">
                <p class="brio-eyebrow">{{ __('Titulaire') }}</p>
                <p class="mt-1 truncate text-xl font-black tracking-tight">{{ $titulaire?->name }}</p>
                <p class="truncate text-sm opacity-70">{{ $titulaire?->email }}</p>
                @if($titulaire?->seat_claimed_at)
                    <p class="mt-1 text-xs opacity-70">
                        {{ __('Siège pris le :date', ['date' => $titulaire->seat_claimed_at->translatedFormat('d F Y')]) }}
                    </p>
                @endif
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <x-ui.badge :tone="$aUnePhrase ? 'success' : 'danger'"
                            :label="$aUnePhrase ? __('Phrase du siège active') : __('Aucune phrase — transfert impossible')" />
                <x-ui.badge :tone="$aUnSecondFacteur ? 'success' : 'warning'"
                            :label="$aUnSecondFacteur ? __('Second facteur exigé') : __('Second facteur non activé')" />
            </div>
        </div>

        @unless($aUnSecondFacteur)
            <p class="mt-4 text-sm opacity-70">
                {{ __('Activez l’authentification à deux facteurs sur ce compte : elle sera alors exigée pour tout transfert, en plus de la phrase.') }}
            </p>
        @endunless
    </x-app-card>

    {{-- UN TRANSFERT ARMÉ : c'est le moment où le délai sert à quelque chose. --}}
    @if($this->transfert)
        <x-app-card class="!border-amber-400/60">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div class="min-w-0">
                    <p class="brio-eyebrow text-amber-600">{{ __('Transfert armé') }}</p>
                    <p class="mt-1 text-lg font-bold">
                        {{ __('Vers :nom', ['nom' => $this->transfert->to?->name ?? '?']) }}
                        <span class="font-normal opacity-70">({{ $this->transfert->to?->email }})</span>
                    </p>
                    <p class="mt-1 text-sm opacity-70">
                        {{ __('Effectif le :date', ['date' => $this->transfert->effective_at->translatedFormat('d/m/Y à H:i')]) }}
                        · {{ $this->transfert->effective_at->diffForHumans() }}
                    </p>
                    <p class="mt-1 text-xs opacity-70">
                        {{ __('Armé depuis :ip', ['ip' => $this->transfert->armed_ip ?? '—']) }}
                        · {{ $this->transfert->armed_at->translatedFormat('d/m/Y à H:i') }}
                    </p>
                    <p class="mt-3 max-w-xl text-sm font-semibold">
                        {{ __('Si vous n’êtes pas à l’origine de ce transfert, annulez-le maintenant et changez votre mot de passe.') }}
                    </p>
                </div>

                <form wire:submit="annulerLeTransfert" class="w-full shrink-0 space-y-2 md:max-w-xs">
                    <label for="phrase-annulation" class="block text-sm font-semibold">{{ __('Phrase du siège') }}</label>
                    <input id="phrase-annulation" wire:model="phrase" type="password" autocomplete="off" class="w-full">
                    @error('phrase') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                    <label for="motif" class="block text-sm font-semibold">{{ __('Motif (facultatif)') }}</label>
                    <input id="motif" wire:model="motifAnnulation" type="text" class="w-full">

                    <button type="submit" class="brio-btn-danger w-full">{{ __('Annuler le transfert') }}</button>
                </form>
            </div>
        </x-app-card>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">

        {{-- LE SEUL GESTE QUI DÉPLACE LE SIÈGE --}}
        <x-app-card class="lg:col-span-2" :title="__('Transférer le siège')"
                    :subtitle="__('La cible doit déjà être un administrateur actif : le siège déplace un pouvoir, il n’en crée pas.')">
            @if($this->transfert)
                <p class="text-sm opacity-70">
                    {{ __('Un transfert est déjà armé. Annulez-le avant d’en armer un autre.') }}
                </p>
            @elseif($this->administrateurs->isEmpty())
                <x-empty-state icon="👤" :title="__('Aucun administrateur à qui transmettre')"
                               :message="__('Le siège ne peut aller qu’à un administrateur actif. Créez-en un d’abord.')" />
            @else
                {{-- PAS DE `wire:submit` ICI : un bouton qui SOUMET emporte le formulaire avant
                     que la modale de confirmation ait repondu. Le geste passe par `wire:click`. --}}
                <div class="space-y-3">
                    <div>
                        <label for="destinataire" class="mb-1 block text-sm font-semibold">{{ __('Nouveau titulaire') }}</label>
                        <select id="destinataire" wire:model="destinataire" class="w-full">
                            <option value="">{{ __('Choisir un administrateur') }}</option>
                            @foreach($this->administrateurs as $administrateur)
                                <option value="{{ $administrateur->id }}" wire:key="admin-{{ $administrateur->id }}">
                                    {{ $administrateur->name }} — {{ $administrateur->email }}
                                </option>
                            @endforeach
                        </select>
                        @error('destinataire') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label for="phrase" class="mb-1 block text-sm font-semibold">{{ __('Phrase du siège') }}</label>
                            <input id="phrase" wire:model="phrase" type="password" autocomplete="off" class="w-full">
                            @error('phrase') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        @if(! empty($this->titulaire?->two_factor_secret))
                            <div>
                                <label for="code" class="mb-1 block text-sm font-semibold">{{ __('Code à deux facteurs') }}</label>
                                <input id="code" wire:model="codeDouble" type="text" inputmode="numeric"
                                       autocomplete="one-time-code" class="w-full">
                            </div>
                        @endif
                    </div>

                    <p class="text-sm opacity-70">
                        {{ __('Le transfert sera armé, pas appliqué : il prendra effet dans :heures heures, et vous pourrez l’annuler jusque-là.', [
                            'heures' => $this->delaiEnHeures,
                        ]) }}
                    </p>

                    <button type="button" wire:click="armerLeTransfert" class="brio-btn-primary w-full"
                            wire:confirm="{{ __('Armer le transfert du siège de super-administrateur ? Vous perdrez le passe-partout à l’échéance.') }}">
                        {{ __('Armer le transfert') }}
                    </button>
                </div>
            @endif
        </x-app-card>

        {{-- CE QUE CECI PROTÈGE, ET CE QU'IL NE PROTÈGE PAS --}}
        <x-app-card :title="__('Ce qui protège ce siège')">
            <ul class="space-y-3 text-sm">
                <li>
                    <span class="font-semibold">{{ __('Un index unique en base') }}</span>
                    <span class="block opacity-70">{{ __('Un second super-administrateur est impossible à écrire, même en SQL direct.') }}</span>
                </li>
                <li>
                    <span class="font-semibold">{{ __('Une phrase distincte du mot de passe') }}</span>
                    <span class="block opacity-70">{{ __('Une session volée ou un mot de passe deviné ne suffisent pas.') }}</span>
                </li>
                <li>
                    <span class="font-semibold">{{ __('Un délai et une annonce') }}</span>
                    <span class="block opacity-70">{{ __('Vous êtes prévenu à l’armement et gardez le délai pour annuler.') }}</span>
                </li>
            </ul>

            <p class="mt-4 border-t border-slate-200/60 pt-3 text-xs opacity-70 dark:border-slate-700">
                {{ __('Ce qu’aucune de ces gardes ne couvre : quiconque tient la base de données ou le serveur peut écrire ce qu’il veut. Protégez ces accès-là en priorité.') }}
            </p>
        </x-app-card>
    </div>
</div>
