{{--
    TROIS GESTES SUR LA LIGNE, LE RESTE SUR LA PAGE.

    Les deux boutons de suivi, la gestion de serie et les huit champs de detail ont demenage
    vers `client.rendezvous.show`. « Laisser un avis » reste : sur un rendez-vous termine, c'est
    la SEULE action possible, et elle disparaitrait sans cela.
--}}
<div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
    <a href="{{ route('client.rendezvous.show', $rdv->id) }}"
       class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
        {{ __('Modifier') }}
    </a>

    @if($rdv->canStillBeEditedByClient())
        <button type="button" wire:click="modifier({{ $rdv->id }})"
            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
            {{ __('Replanifier') }}
        </button>

        <button type="button" wire:click="demanderAnnulation({{ $rdv->id }})"
            class="brio-btn-danger">
            {{ __('Annuler') }}
        </button>
    @endif

    @if($rdv->status === 'termine' && $rdv->feedback)
        <span class="rounded-xl bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ __('Avis déposé') }}
        </span>
    @elseif($rdv->status === 'termine')
        <a href="{{ route('feedback.create', $rdv->id) }}"
           class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            {{ __('Laisser un avis') }}
        </a>
    @endif
</div>
