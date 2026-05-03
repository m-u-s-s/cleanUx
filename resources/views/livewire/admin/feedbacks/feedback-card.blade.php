<article class="rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm transition hover:border-indigo-200 hover:bg-white hover:shadow-md">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-black text-amber-700 ring-1 ring-amber-200">
                    ⭐ {{ $feedback->note }}/5
                </span>

                @if((int) $feedback->note <= 2)
                    <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-black text-rose-700 ring-1 ring-rose-200">
                        À surveiller
                    </span>
                @endif

                @if(filled($feedback->reponse_admin))
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-200">
                        Répondu
                    </span>
                @else
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600 ring-1 ring-slate-200">
                        Sans réponse
                    </span>
                @endif
            </div>

            <h3 class="mt-3 text-lg font-black text-slate-900">
                {{ $feedback->client?->name ?? 'Client inconnu' }}
            </h3>

            <p class="mt-1 text-sm text-slate-600">
                Employé :
                <span class="font-semibold">
                    {{ $feedback->rendezVous?->employe?->name ?? 'Non assigné' }}
                </span>
            </p>

            <p class="mt-1 text-sm text-slate-600">
                Service :
                <span class="font-semibold">
                    {{ $feedback->rendezVous?->service_display_name ?? 'Service non précisé' }}
                </span>
            </p>

            <p class="mt-1 text-sm text-slate-500">
                {{ optional($feedback->created_at)->format('d/m/Y H:i') }}
                @if($feedback->rendezVous?->status)
                    · Statut RDV : {{ $feedback->rendezVous->status }}
                @endif
            </p>
        </div>

        <div class="shrink-0 text-right">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Feedback #{{ $feedback->id }}
            </p>

            @if($feedback->rendezVous?->serviceZone)
                <p class="mt-1 text-sm font-bold text-slate-700">
                    {{ $feedback->rendezVous->serviceZone->name }}
                </p>
            @endif
        </div>
    </div>

    <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
            Commentaire client
        </p>

        <p class="mt-2 text-sm leading-6 text-slate-700">
            {{ $feedback->commentaire ?: 'Aucun commentaire écrit.' }}
        </p>
    </div>

    <div class="mt-5">
        <label class="mb-1 block text-sm font-bold text-slate-700">
            Réponse admin
        </label>

        <textarea
            wire:model.live.debounce.700ms="reponse.{{ $feedback->id }}"
            rows="3"
            placeholder="Écrivez une réponse courte, professionnelle et utile au client…"
            class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>

        <p class="mt-2 text-xs text-slate-500">
            Sauvegarde automatique après modification.
        </p>
    </div>
</article>
