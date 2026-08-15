{{--
    Le bandeau qui dit pourquoi aucune mission n'arrivera — et où aller pour que ça change.

    Il ne s'affiche que tant que le compte n'est pas vérifié. Une fois la porte du dispatch
    franchie, il disparaît : un bandeau permanent devient du décor.
--}}
{{-- Racine toujours présente : Livewire exige une balise racine, même quand il n'y a rien à dire. --}}
<div>
@if ($afficher)
    <div @class([
        'rounded-2xl px-4 py-3 text-sm',
        'bg-sky-50 text-sky-900' => $enRelecture,
        'bg-amber-50 text-amber-900' => ! $enRelecture,
    ]) data-test="banniere-dossier-prestataire">
        @if ($enRelecture)
            {{-- Tout est déposé : on ne réclame rien, on dit où ça en est. --}}
            <p class="font-medium">Votre dossier est complet. Nous le relisons.</p>
            <p class="mt-1">
                Vous recevrez vos premières missions dès qu’il sera validé. Aucune action de votre
                part n’est nécessaire.
            </p>
        @else
            <p class="font-medium">
                Vous ne recevrez pas encore de mission : votre dossier n’est pas terminé.
            </p>

            @if (count($manquants))
                <p class="mt-1">
                    Il reste&nbsp;: {{ implode(', ', $manquants) }}@if ($reste > 0), et {{ $reste }}
                    autre{{ $reste > 1 ? 's' : '' }} point{{ $reste > 1 ? 's' : '' }}@endif.
                </p>
            @endif
        @endif

        {{--
            LE LIEN, DANS LES DEUX CAS.

            Même quand il n'y a rien à faire, le prestataire veut pouvoir vérifier ce qu'il a
            envoyé. C'est précisément ce lien qui manquait : l'assistant existait, mais aucune page
            du portail n'y menait.
        --}}
        <a href="{{ route('provider.onboarding') }}" wire:navigate
            class="mt-2 inline-flex min-h-[44px] items-center font-semibold underline underline-offset-4">
            {{ $enRelecture ? 'Revoir mon dossier' : 'Compléter mon dossier' }}
        </a>
    </div>
@endif
</div>
