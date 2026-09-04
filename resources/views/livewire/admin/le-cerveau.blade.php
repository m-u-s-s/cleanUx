{{--
    LE CERVEAU.

    Le plus grave en tête, toujours. Un écran qui noie une alerte rouge sous douze remarques
    neutres ne sert à rien : personne ne lit jusqu'en bas.

    Chaque geste passe par DEUX clics. Entre les deux, on affiche ce qu'il fait, ce qu'il implique
    et s'il est réversible — un bouton qui ne dit pas ce qu'il coûte n'est pas un conseil.
--}}
<div class="space-y-6">

    <x-page-shell
        :eyebrow="__('Propriété de la plateforme')"
        :title="__('Le cerveau')"
        :subtitle="__('Ce que les chiffres disent, et ce qu’on peut en faire. Aucune intelligence artificielle : chaque constat est une soustraction que vous pouvez refaire.')" />

    @if($message)
        <div class="brio-alerte brio-alerte-success !mb-0"><span aria-hidden="true">✅</span><span>{{ $message }}</span></div>
    @endif

    @if($erreur)
        <div class="brio-alerte brio-alerte-danger !mb-0"><span aria-hidden="true">⚠️</span><span>{{ $erreur }}</span></div>
    @endif

    {{-- LE DÉTAIL D'UN GESTE : le second clic, celui qui compte. --}}
    @if($this->gesteDetaille)
        @php($geste = $this->gesteDetaille)
        <x-app-card class="!border-amber-400/60">
            <p class="brio-eyebrow text-amber-600">{{ __('Avant d’appliquer') }}</p>
            <p class="mt-1 text-lg font-bold">{{ $geste->libelle }}</p>

            <dl class="mt-3 space-y-2 text-sm">
                <div>
                    <dt class="font-semibold">{{ __('Ce que ça fait') }}</dt>
                    <dd class="opacity-80">{{ $geste->fait }}</dd>
                </div>
                <div>
                    <dt class="font-semibold">{{ __('Ce que ça implique') }}</dt>
                    <dd class="opacity-80">{{ $geste->implique }}</dd>
                </div>
                <div>
                    <dt class="font-semibold">{{ __('Réversible ?') }}</dt>
                    <dd class="opacity-80">
                        {{ $geste->reversible
                            ? __('Oui — vous pouvez revenir en arrière depuis le même écran.')
                            : __('NON. Ce geste ne se défait pas.') }}
                    </dd>
                </div>
            </dl>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" wire:click="appliquerLeGeste" class="brio-btn-primary">
                    {{ __('Appliquer') }}
                </button>
                <button type="button" wire:click="abandonnerLeGeste" class="brio-btn-ligne">
                    {{ __('Ne rien faire') }}
                </button>
            </div>
        </x-app-card>
    @endif

    <x-app-card>
        <div class="mb-4 flex flex-wrap gap-2">
            <button type="button" wire:click="$set('domaine', '')"
                    class="{{ $domaine === '' ? 'brio-btn-primary' : 'brio-btn-secondary' }} !px-3 !py-1.5 !text-xs">
                {{ __('Tout') }} ({{ array_sum($this->compteurs) }})
            </button>
            @foreach(\App\Services\Cerveau\Cerveau::DOMAINES as $cle => $nom)
                <button type="button" wire:click="$set('domaine', '{{ $cle }}')"
                        class="{{ $domaine === $cle ? 'brio-btn-primary' : 'brio-btn-secondary' }} !px-3 !py-1.5 !text-xs">
                    {{ $nom }} ({{ $this->compteurs[$cle] ?? 0 }})
                </button>
            @endforeach
        </div>

        <div class="space-y-3">
            @forelse($this->recommandations as $recommandation)
                @php($tons = ['danger' => 'danger', 'attention' => 'warning', 'bien' => 'success', 'neutre' => 'neutral'])
                <div class="brio-card !p-4" wire:key="reco-{{ $loop->index }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <p class="font-bold">{{ $recommandation->titre }}</p>
                        <div class="flex shrink-0 items-center gap-2">
                            <x-ui.badge tone="neutral"
                                        :label="\App\Services\Cerveau\Cerveau::DOMAINES[$recommandation->domaine] ?? $recommandation->domaine" />
                            <x-ui.badge :tone="$tons[$recommandation->ton] ?? 'neutral'"
                                        :label="ucfirst($recommandation->ton)" />
                        </div>
                    </div>

                    {{-- LE CHIFFRE D'ABORD : c'est lui qui rend le conseil contestable. --}}
                    <p class="mt-2 text-sm opacity-80">{{ $recommandation->constat }}</p>
                    <p class="mt-2 text-sm">{{ $recommandation->geste }}</p>

                    @if($recommandation->aUnBouton())
                        <button type="button"
                                wire:click="preparerLeGeste('{{ $recommandation->gesteApplicable }}', {{ \Illuminate\Support\Js::from($recommandation->arguments) }})"
                                class="brio-btn-ligne mt-3 !text-xs">
                            {{ __('Voir ce que ce geste implique') }}
                        </button>
                    @endif
                </div>
            @empty
                <x-empty-state icon="🧠" :title="__('Rien à signaler')"
                               :message="__('Aucun signal sur les trente derniers jours. C’est une bonne nouvelle — ou le signe qu’il n’y a pas encore assez d’activité pour en tirer quoi que ce soit.')" />
            @endforelse
        </div>
    </x-app-card>

    <x-app-card :title="__('Ce que le cerveau ne fera jamais')">
        <ul class="space-y-2 text-sm">
            <li>
                <span class="font-semibold">{{ __('Sortir de l’argent.') }}</span>
                <span class="opacity-80">{{ __('Aucun remboursement, aucun virement, aucune capture. Une automatisation qui déplace de l’argent finit par le déplacer une fois de trop, et un remboursement rendu à tort ne se reprend pas.') }}</span>
            </li>
            <li>
                <span class="font-semibold">{{ __('Suspendre un compte.') }}</span>
                <span class="opacity-80">{{ __('Il propose une mise en revue — un dossier sur une pile. Un compte bloqué à tort, c’est un client perdu, un litige, et souvent un avis public.') }}</span>
            </li>
            <li>
                <span class="font-semibold">{{ __('Agir sans vous.') }}</span>
                <span class="opacity-80">{{ __('Chaque geste attend deux clics, et le second n’arrive qu’après avoir lu ce qu’il implique.') }}</span>
            </li>
        </ul>
    </x-app-card>
</div>
