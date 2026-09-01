<div class="mx-auto max-w-4xl">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Qualité et matériel</h1>
        <p class="mt-1 text-sm text-slate-500">
            Qui peut travailler demain, et avec quoi.
        </p>
    </header>

    @if ($refus)
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        {{ $refus }}
    </div>
    @endif

    @if ($peutVoirLaFlotte && $echeances->isNotEmpty())
    {{-- Découvrir l'expiration quand le moteur refuse l'assignation, c'est la découvrir trop tard. --}}
    <div class="mb-8 rounded-2xl border border-rose-200 bg-rose-50 p-5">
        <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-rose-800">Échéances</h2>
        <ul class="space-y-1 text-sm text-rose-900">
            @foreach ($echeances as $certification)
            <li>
                <span class="font-semibold">{{ $certification->certification_type }}</span>
                — expire le {{ $certification->expires_at?->format('d/m/Y') }}
                ({{ $certification->subject_type }} #{{ $certification->subject_id }})
            </li>
            @endforeach
        </ul>
        <p class="mt-2 text-xs text-rose-800">
            Une certification expirée fait refuser l'assignation par le moteur. Renouvelez avant, pas
            le matin même.
        </p>
    </div>
    @endif

    @if ($peutVoirLaQualite)
    {{-- Le score interne --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Score qualité interne
        </h2>

        <div class="brio-table-cadre">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-5 py-2 font-semibold">Personne</th>
                        <th class="px-5 py-2 text-right font-semibold tabular-nums">Missions</th>
                        <th class="px-5 py-2 text-right font-semibold tabular-nums">Inspection</th>
                        <th class="px-5 py-2 text-right font-semibold tabular-nums">Satisfaction</th>
                        <th class="px-5 py-2 text-right font-semibold tabular-nums">Ponctualité</th>
                        <th class="px-5 py-2 text-right font-semibold tabular-nums">Score</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($scores as $ligne)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-5 py-3 font-semibold text-slate-900">{{ $ligne['name'] ?? '—' }}</td>
                        <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ $ligne['missions_count'] }}</td>
                        <td class="px-5 py-3 text-right tabular-nums text-slate-600">
                            {{ $ligne['inspection_score'] !== null ? $ligne['inspection_score'].' %' : '—' }}
                        </td>
                        <td class="px-5 py-3 text-right tabular-nums text-slate-600">
                            {{ $ligne['satisfaction_score'] !== null ? $ligne['satisfaction_score'].' %' : '—' }}
                        </td>
                        <td class="px-5 py-3 text-right tabular-nums text-slate-600">
                            {{ $ligne['punctuality_score'] !== null ? $ligne['punctuality_score'].' %' : '—' }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if ($ligne['has_enough_data'] && $ligne['score'] !== null)
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold tabular-nums
                                {{ $ligne['score'] >= 80 ? 'bg-emerald-50 text-emerald-700' : ($ligne['score'] >= 60 ? 'bg-amber-50 text-amber-800' : 'bg-rose-50 text-rose-700') }}">
                                {{ $ligne['score'] }} %
                            </span>
                            @else
                            {{-- Une moyenne sur une mission est du bruit affiché avec deux décimales. --}}
                            <span class="text-xs text-slate-400">Pas assez de données</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">
                            Aucun membre actif.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
            Trois sources existantes — inspections, avis clients, heure d'arrivée relevée — et aucune
            nouvelle collecte. En dessous de {{ $missionsMinimum }} missions, aucun score n'est
            calculé : il serait lu comme un jugement. Ce score ne sort pas de votre société.
        </p>
    </div>
    @endif

    @if ($peutVoirLaFlotte)
    {{-- La flotte --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Véhicules
        </h2>

        @forelse ($vehicules as $vehicule)
        <div class="flex items-center justify-between gap-3 border-b border-slate-50 px-5 py-3 last:border-0">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900">
                    {{ $vehicule->plate ?? $vehicule->code }}
                </p>
                <p class="truncate text-xs text-slate-500">
                    {{ trim(($vehicule->brand ?? '').' '.($vehicule->model ?? '')) ?: 'Modèle non renseigné' }}
                    @if ($vehicule->currentProvider) — {{ $vehicule->currentProvider->name }} @endif
                </p>
            </div>

            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold
                {{ $vehicule->status === 'available' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                {{ $vehicule->status }}
            </span>
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-slate-500">
            Aucun véhicule déclaré. Jusqu'ici, seule la plateforme pouvait en enregistrer.
        </p>
        @endforelse
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Équipements
        </h2>

        @forelse ($equipements as $equipement)
        <div class="flex items-center justify-between gap-3 border-b border-slate-50 px-5 py-3 last:border-0">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900">{{ $equipement->name }}</p>
                <p class="truncate text-xs text-slate-500">
                    {{ $equipement->equipment_type ?? 'Type non renseigné' }}
                </p>
            </div>

            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                {{ $equipement->status }}
            </span>
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-slate-500">
            Aucun équipement déclaré.
        </p>
        @endforelse
    </div>
    @endif
</div>
