<div class="mx-auto max-w-5xl">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Heures et rentabilité</h1>
        <p class="mt-1 text-sm text-slate-500">
            Ce qui a été travaillé, et ce que ça vous a coûté.
        </p>
    </header>

    @if ($refus)
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        {{ $refus }}
    </div>
    @endif

    {{-- Période --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5">
        <div class="grid gap-4 sm:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Du</span>
                <input type="date" wire:model.live="du"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Au</span>
                <input type="date" wire:model.live="au"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Ventiler par</span>
                <select wire:model.live="ventilation"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    <option value="site">Site</option>
                    <option value="team">Équipe</option>
                    <option value="agency">Implantation</option>
                </select>
            </label>
        </div>

        @if ($peutGerer)
        <button type="button" wire:click="exporter"
            class="mt-4 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            Exporter pour la paie (CSV)
        </button>
        @endif
    </div>

    @if ($peutGerer && $enAttente->isNotEmpty())
    {{-- Corrections à approuver --}}
    <div class="mb-8 rounded-2xl border border-amber-200 bg-amber-50/50">
        <h2 class="border-b border-amber-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-amber-800">
            Corrections en attente d'approbation
        </h2>

        @foreach ($enAttente as $ligne)
        <div class="flex items-center justify-between border-b border-amber-100 px-5 py-3 last:border-0">
            <div>
                <p class="text-sm font-semibold text-slate-900">{{ $ligne->user?->name }}</p>
                <p class="text-xs text-slate-600">
                    {{ $ligne->started_at?->format('d/m/Y H:i') }} — {{ $ligne->worked_minutes }} min
                    @if ($ligne->notes) — {{ $ligne->notes }} @endif
                </p>
            </div>

            <div class="flex gap-2">
                <button type="button" wire:click="statuer({{ $ligne->id }}, true)"
                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                    Approuver
                </button>
                <button type="button" wire:click="statuer({{ $ligne->id }}, false)"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-white">
                    Refuser
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- La feuille d'heures --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Feuille d'heures
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-5 py-2 font-semibold">Personne</th>
                        <th class="px-5 py-2 font-semibold">Lignes</th>
                        <th class="px-5 py-2 text-right font-semibold tabular-nums">Minutes</th>
                        <th class="px-5 py-2 text-right font-semibold tabular-nums">Heures</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($feuille as $ligne)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-5 py-3 font-semibold text-slate-900">{{ $ligne['name'] }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $ligne['entries_count'] }}</td>
                        <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ $ligne['worked_minutes'] }}</td>
                        <td class="px-5 py-3 text-right tabular-nums font-semibold text-slate-900">{{ number_format($ligne['worked_hours'], 2, ',', ' ') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-5 py-8 text-center text-sm text-slate-500">
                            Aucune heure retenue sur cette période.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
            Une correction en attente ne compte pas : payer avant approbation reviendrait à ne jamais
            approuver.
        </p>
    </div>

    @if ($peutVoirLaMarge)
    {{-- La rentabilité --}}
    <div class="rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Rentabilité
        </h2>

        @if ($sansPointage > 0)
        {{-- Annoncé, jamais masqué : une mission sans heures afficherait une marge de 100 %. --}}
        <div class="border-b border-slate-100 bg-amber-50 px-5 py-3 text-xs font-semibold text-amber-900">
            {{ $sansPointage }} mission(s) sans pointage sur la période : leur marge n'est pas calculable
            et n'est donc pas fiable.
        </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-5 py-2 font-semibold">Clé</th>
                        <th class="px-5 py-2 text-right font-semibold tabular-nums">Missions</th>
                        <th class="px-5 py-2 text-right font-semibold tabular-nums">Produit</th>
                        <th class="px-5 py-2 text-right font-semibold tabular-nums">Coût</th>
                        <th class="px-5 py-2 text-right font-semibold tabular-nums">Marge</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rentabilite as $ligne)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-5 py-3 font-semibold text-slate-900">
                            {{ $ligne['key'] ?: 'Non ventilé' }}
                            @if ($ligne['missions_without_timesheet'] > 0)
                            <span class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                                {{ $ligne['missions_without_timesheet'] }} sans heures
                            </span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ $ligne['missions_count'] }}</td>
                        <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ number_format($ligne['revenue_cents'] / 100, 2, ',', ' ') }} €</td>
                        <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ number_format($ligne['total_cost_cents'] / 100, 2, ',', ' ') }} €</td>
                        <td class="px-5 py-3 text-right tabular-nums font-semibold {{ $ligne['margin_cents'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ number_format($ligne['margin_cents'] / 100, 2, ',', ' ') }} €
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">
                            Aucune mission sur cette période.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
            Le coût de main-d'œuvre s'appuie sur le taux horaire déclaré par votre société, à défaut
            {{ number_format($tauxParDefautCents / 100, 2, ',', ' ') }} € — une hypothèse prudente,
            pas un salaire connu de la plateforme.
        </p>
    </div>
    @endif
</div>
