{{--
    LA LIGNE DIT L'ESSENTIEL, LA PAGE DIT LE RESTE.

    Elle portait huit champs de detail, la remarque du client et le panneau de suivi entier :
    une page de dix rendez-vous devenait interminable. Tout cela vit maintenant sur
    `client.rendezvous.show`, ou le client gere son intervention.
--}}
<div class="rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0">
            {{-- LE TITRE EST LE LIEN : toute la ligne mene au detail, sans bouton supplementaire. --}}
            <a href="{{ route('client.rendezvous.show', $rdv->id) }}"
               class="text-lg font-semibold text-slate-900 hover:underline dark:text-slate-100"
               aria-label="{{ __('Ouvrir le rendez-vous') }} — {{ $rdv->service_display_name }}">
                {{ $rdv->service_display_name }}
            </a>

            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                {{ $rdv->date }} à {{ $rdv->heure }}
                @if($rdv->ville)
                    · {{ $rdv->ville }}
                @endif
            </p>

            <p class="text-sm text-slate-600 dark:text-slate-400">
                {{ $rdv->employe->name ?? __('Prestataire à confirmer') }}
            </p>

            <p class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-500">
                {{ $rdv->booking_reference ?? '#'.$rdv->id }}
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <x-badge :status="$rdv->status" />
            <x-priority-badge :priority="$rdv->priorite" />
        </div>
    </div>

    @include('livewire.client.rendezvous.actions', ['rdv' => $rdv])
</div>
