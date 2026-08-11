<div class="mx-auto max-w-3xl px-4 py-8">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Ma protection</h1>
        <p class="mt-1 text-sm text-slate-500">
            Ce qui vous couvre, ce que coûterait une annulation, et où en sont vos réclamations.
        </p>
    </header>

    {{-- L'assurance --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Assurance</h2>
            @if ($protection['insurance']['active_count'] > 0)
            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                {{ number_format($protection['insurance']['total_coverage_cents'] / 100, 0, ',', ' ') }} € couverts
            </span>
            @endif
        </div>

        @forelse ($protection['insurance']['policies'] as $police)
        <div class="flex items-center justify-between border-b border-slate-50 px-5 py-3 last:border-0">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900">
                    Police {{ $police['policy_number'] ?? '—' }}
                </p>
                <p class="text-xs text-slate-500">
                    Intervention {{ $police['booking_reference'] ?? '—' }}
                    @if ($police['effective_until']) · jusqu'au {{ $police['effective_until'] }} @endif
                </p>
            </div>
            <span class="shrink-0 text-sm font-semibold tabular-nums text-slate-900">
                {{ number_format($police['coverage_amount_cents'] / 100, 0, ',', ' ') }} €
            </span>
        </div>
        @empty
        <div class="px-5 py-6">
            <p class="text-sm text-slate-600">
                Aucune intervention assurée en cours.
            </p>
            {{-- On le DIT plutôt que d'afficher un bloc vide : découvrir qu'on n'était pas couvert
                 au moment du sinistre est exactement ce que cette page doit éviter. --}}
            <p class="mt-1 text-xs text-slate-500">
                L'assurance se souscrit au moment de la réservation.
            </p>
        </div>
        @endforelse
    </div>

    {{-- L'annulation --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Si vous annuliez maintenant
        </h2>

        @forelse ($protection['cancellation']['quotes'] as $ligne)
        <div class="border-b border-slate-50 px-5 py-3 last:border-0">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">
                        {{ $ligne['booking_reference'] ?? 'Intervention' }}
                    </p>
                    <p class="text-xs text-slate-500">
                        @if ($ligne['scheduled_at'])
                        {{ \Illuminate\Support\Carbon::parse($ligne['scheduled_at'])->format('d/m/Y à H:i') }}
                        · dans {{ $ligne['hours_before'] }} h
                        @endif
                    </p>
                </div>

                @php
                    $frais = data_get($ligne, 'policy.fee_cents')
                        ?? data_get($ligne, 'policy.fee')
                        ?? null;
                @endphp
                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $frais ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-700' }}">
                    @if ($frais)
                    {{ number_format(((float) $frais) / 100, 2, ',', ' ') }} € de frais
                    @else
                    Sans frais
                    @endif
                </span>
            </div>
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-slate-500">
            Aucune intervention à venir.
        </p>
        @endforelse

        <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
            Un montant se comprend, un barème de paliers se lit et ne se retient pas : ces chiffres
            sont calculés à l'heure où vous les regardez.
        </p>
    </div>

    {{-- Les litiges --}}
    <div class="rounded-2xl border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Mes réclamations</h2>
            @if ($protection['disputes']['open_count'] > 0)
            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">
                {{ $protection['disputes']['open_count'] }} en cours
            </span>
            @endif
        </div>

        @forelse ($protection['disputes']['cases'] as $dossier)
        <div class="flex items-center justify-between border-b border-slate-50 px-5 py-3 last:border-0">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900">
                    {{ $dossier['subject'] ?? $dossier['reference'] }}
                </p>
                <p class="text-xs text-slate-500">
                    Ouverte le {{ $dossier['opened_at'] }}
                    @if ($dossier['resolved_at']) · résolue le {{ $dossier['resolved_at'] }} @endif
                </p>
            </div>
            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                {{ $dossier['status'] }}
            </span>
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-slate-500">
            Aucune réclamation. Vous pouvez en ouvrir une depuis une intervention terminée.
        </p>
        @endforelse
    </div>
</div>
