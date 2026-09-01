<div class="mx-auto max-w-5xl">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Pilotage</h1>
        <p class="mt-1 text-sm text-slate-500">
            Budgets, approbations, niveau de service — et l'export pour votre comptable.
        </p>
    </header>

    @if ($refus)
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        {{ $refus }}
    </div>
    @endif

    {{-- Période --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <label for="pilotage-du" class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Du</span>
                <input id="pilotage-du" type="date" wire:model.live="du"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>

            <label for="pilotage-au" class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Au</span>
                <input id="pilotage-au" type="date" wire:model.live="au"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>
        </div>

        @if ($peutTelecharger)
        <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" wire:click="exporter('csv')"
                class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Exporter en CSV
            </button>
            <button type="button" wire:click="exporter('fec')"
                class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Exporter au format FEC
            </button>
        </div>
        <p class="mt-2 text-xs text-slate-500">
            Vos factures, et elles seules. Jusqu'ici il fallait les télécharger une par une.
        </p>
        @endif
    </div>

    {{-- Le niveau de service --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Niveau de service
        </h2>

        <div class="grid gap-4 p-5 sm:grid-cols-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Réalisation</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $sla['completion_rate'] }} %</p>
                <p class="text-xs text-slate-500">{{ $sla['bookings_count'] }} intervention(s)</p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Ponctualité</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">
                    {{ $sla['punctuality_rate'] !== null ? $sla['punctuality_rate'].' %' : '—' }}
                </p>
                {{-- Annoncées, jamais fondues : les compter comme des retards punirait un GPS
                     coupé ; comme des arrivées à l'heure, l'inverse. --}}
                <p class="text-xs text-slate-500">
                    {{ $sla['without_arrival_data'] }} sans arrivée relevée
                </p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Annulation</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $sla['cancellation_rate'] }} %</p>
            </div>
        </div>

        @if (count($slaParLocal) > 0)
        <div class="brio-table-cadre border-t border-slate-100">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-5 py-2 font-semibold">Local</th>
                        <th class="px-5 py-2 text-right font-semibold tabular-nums">Interventions</th>
                        <th class="px-5 py-2 text-right font-semibold tabular-nums">Réalisation</th>
                        <th class="px-5 py-2 text-right font-semibold tabular-nums">Ponctualité</th>
                        <th class="px-5 py-2 text-right font-semibold tabular-nums">Engagé</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($slaParLocal as $ligne)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-5 py-3 font-semibold text-slate-900">{{ $ligne['site_name'] ?? 'Non rattaché' }}</td>
                        <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ $ligne['bookings_count'] }}</td>
                        <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ $ligne['completion_rate'] }} %</td>
                        <td class="px-5 py-3 text-right tabular-nums text-slate-600">
                            {{ $ligne['punctuality_rate'] !== null ? $ligne['punctuality_rate'].' %' : '—' }}
                        </td>
                        <td class="px-5 py-3 text-right tabular-nums font-semibold text-slate-900">
                            <x-money :amount="(float) ($ligne['committed_cents'] / 100)" />
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    @if ($peutApprouver)
    {{-- Les approbations --}}
    <div class="mb-8 rounded-2xl border border-amber-200 bg-amber-50/50">
        <h2 class="border-b border-amber-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-amber-800">
            Demandes à approuver
        </h2>

        @forelse ($approbations as $demande)
        <div class="flex items-center justify-between gap-3 border-b border-amber-100 px-5 py-3 last:border-0">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900">
                    {{ $demande->organizationSite?->name ?? 'Sans local' }}
                    @if ($demande->trade) — {{ $demande->trade->name }} @endif
                </p>
                <p class="text-xs text-slate-600">
                    Demandé par {{ $demande->clientUser?->name ?? 'un membre' }}
                    @if ($demande->scheduled_at)
                    · pour le {{ $demande->scheduled_at->format('d/m/Y à H:i') }}
                    @endif
                </p>
            </div>

            <div class="flex shrink-0 gap-2">
                <button type="button" wire:click="approuver({{ $demande->id }})"
                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                    Approuver
                </button>
                <button type="button" wire:click="refuserLaDemande({{ $demande->id }})"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-white">
                    Refuser
                </button>
            </div>
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-slate-600">
            Aucune demande en attente.
        </p>
        @endforelse

        @if ($approbations->isNotEmpty())
        <div class="border-t border-amber-100 px-5 py-3">
            <label for="motif-refus" class="block">
                <span class="mb-1 block text-xs font-semibold text-slate-600">Motif du refus</span>
                <input id="motif-refus" type="text" wire:model="motifRefus"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>
            <p class="mt-2 text-xs text-slate-500">
                Approuver lance immédiatement la recherche d'un professionnel.
            </p>
        </div>
        @endif
    </div>
    @endif

    @if ($peutVoirLaFinance)
    {{-- Les budgets --}}
    <div class="rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Budgets en cours
        </h2>

        @forelse ($budgets as $budget)
        <div class="border-b border-slate-50 px-5 py-4 last:border-0">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-900">
                        {{ $budget['site_name'] ?? 'Toute la société' }}
                    </p>
                    <p class="text-xs text-slate-500">
                        Du {{ $budget['period_start'] }} au {{ $budget['period_end'] }}
                    </p>
                </div>

                @if ($budget['is_exceeded'])
                <span class="shrink-0 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
                    Dépassé
                </span>
                @elseif ($budget['is_warning'])
                <span class="shrink-0 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">
                    Bientôt atteint
                </span>
                @endif
            </div>

            <div class="mt-2 flex items-center justify-between text-sm">
                <span class="text-slate-600">
                    <x-money :amount="(float) ($budget['committed_cents'] / 100)" />
                    sur <x-money :amount="(float) ($budget['limit_cents'] / 100)" />
                </span>
                <span class="font-semibold tabular-nums text-slate-900">{{ $budget['usage_percent'] }} %</span>
            </div>

            <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                {{--
                    `amber-400` ET NON `amber-500` : le garde-fou de thème réserve les fonds pleins
                    `amber-500/600` à l'accent d'un espace — l'ambre du prestataire était une marque
                    parallèle, née d'aucune décision. L'usage SÉMANTIQUE reste permis, dans les tons
                    qui ne se confondent pas avec un accent.
                --}}
                <div class="h-full rounded-full {{ $budget['is_exceeded'] ? 'bg-rose-400' : ($budget['is_warning'] ? 'bg-amber-400' : 'bg-emerald-400') }}"
                    style="width: {{ min(100, max(2, $budget['usage_percent'])) }}%"></div>
            </div>
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-slate-500">
            Aucun budget défini. Sans plafond, le dépassement se découvre à la facture.
        </p>
        @endforelse

        @if ($peutGererLeBudget)
        <div class="border-t border-slate-100 p-5">
            <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Définir un budget</h3>

            <div class="grid gap-4 sm:grid-cols-3">
                <label for="budget-local" class="block">
                    <span class="mb-1 block text-sm font-semibold text-slate-900">Local</span>
                    <select id="budget-local" wire:model="budgetSiteId"
                        class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                        <option value="">Toute la société</option>
                        @foreach ($locaux as $local)
                        <option value="{{ $local->id }}">{{ $local->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label for="budget-plafond" class="block">
                    <span class="mb-1 block text-sm font-semibold text-slate-900">Plafond mensuel (€)</span>
                    <input id="budget-plafond" type="text" wire:model="budgetPlafond" placeholder="5000"
                        class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    @error('budgetPlafond')
                    <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </label>

                <label for="budget-seuil" class="block">
                    <span class="mb-1 block text-sm font-semibold text-slate-900">Alerter à (%)</span>
                    <input id="budget-seuil" type="number" min="10" max="100" wire:model="budgetSeuil"
                        class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    @error('budgetSeuil')
                    <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </label>
            </div>

            <button type="button" wire:click="definirLeBudget"
                class="mt-4 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                Enregistrer le budget
            </button>

            <p class="mt-3 text-xs text-slate-500">
                Le plafond alerte, il ne bloque pas : une intervention refusée parce qu'un budget est
                atteint, c'est une fuite d'eau qu'on laisse couler pour une ligne comptable.
            </p>
        </div>
        @endif
    </div>
    @endif
</div>
