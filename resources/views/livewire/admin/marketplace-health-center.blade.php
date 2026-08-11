<div class="py-6">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Marché</p>
                <h1 class="text-2xl font-black text-slate-900">Santé, prévision et rattrapage</h1>
                <p class="text-sm text-slate-500">
                    Où le marché décroche, ce qu'il faudra y servir, et les clients déjà perdus.
                </p>
            </div>

            <label for="sante-jours" class="flex items-center gap-2">
                <span class="text-xs text-slate-500">Fenêtre</span>
                <select id="sante-jours" wire:model.live="jours"
                    class="rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    <option value="7">7 jours</option>
                    <option value="30">30 jours</option>
                    <option value="90">90 jours</option>
                </select>
            </label>
        </div>

        @if ($refus)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ $refus }}</div>
        @endif

        @if ($confirmation)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ $confirmation }}</div>
        @endif

        {{-- Le résumé --}}
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase text-slate-500">Recherches</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($resume['searches_count']) }}</p>
            </div>

            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                {{-- LE SEUL CHIFFRE QUI COMMANDE UNE ACTION : il dit où recruter. --}}
                <p class="text-xs font-bold uppercase text-slate-500">Sans candidat</p>
                <p class="text-2xl font-black {{ ($resume['no_candidate_rate'] ?? 0) >= 20 ? 'text-rose-600' : 'text-slate-900' }}">
                    {{ $resume['no_candidate_rate'] !== null ? $resume['no_candidate_rate'].' %' : '—' }}
                </p>
            </div>

            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase text-slate-500">Zones à risque</p>
                <p class="text-2xl font-black text-amber-600">{{ count($resume['zones_at_risk']) }}</p>
            </div>

            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                {{-- Une zone sans demande n'est pas une zone en bonne santé : c'est une zone où
                     l'on n'a jamais rien vendu, et il faut la voir. --}}
                <p class="text-xs font-bold uppercase text-slate-500">Zones sans demande</p>
                <p class="text-2xl font-black text-slate-900">{{ $resume['zones_without_data'] }}</p>
            </div>
        </div>

        {{-- Par zone --}}
        <div class="overflow-hidden rounded-2xl border bg-white shadow-sm">
            <h2 class="border-b px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
                Offre et demande par zone
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-5 py-2 font-semibold">Zone</th>
                            <th class="px-5 py-2 text-right font-semibold tabular-nums">Recherches</th>
                            <th class="px-5 py-2 text-right font-semibold tabular-nums">Sans candidat</th>
                            <th class="px-5 py-2 text-right font-semibold tabular-nums">Assignation</th>
                            <th class="px-5 py-2 text-right font-semibold tabular-nums">Prestataires</th>
                            <th class="px-5 py-2 text-right font-semibold tabular-nums">Demande/pro</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($zones as $zone)
                        <tr class="border-b last:border-0 {{ ($zone['no_candidate_rate'] ?? 0) >= 20 ? 'bg-rose-50/50' : '' }}">
                            <td class="px-5 py-3 font-semibold text-slate-900">
                                {{ $zone['zone_name'] }}
                                @unless ($zone['has_data'])
                                <span class="ml-2 text-xs font-normal text-slate-400">aucune demande</span>
                                @endunless
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ $zone['searches_count'] }}</td>
                            <td class="px-5 py-3 text-right tabular-nums font-semibold {{ ($zone['no_candidate_rate'] ?? 0) >= 20 ? 'text-rose-700' : 'text-slate-600' }}">
                                {{ $zone['no_candidate_rate'] !== null ? $zone['no_candidate_rate'].' %' : '—' }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-slate-600">
                                {{ $zone['median_assignment_seconds'] !== null ? $zone['median_assignment_seconds'].' s' : '—' }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ $zone['providers_online'] }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-slate-600">
                                {{ $zone['demand_per_provider'] ?? '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="border-t px-5 py-3 text-xs text-slate-500">
                Le temps d'assignation est une médiane : une recherche de quarante minutes un
                dimanche soir décalerait une moyenne au point de la rendre inutilisable.
            </p>
        </div>

        {{-- La prévision --}}
        <div class="overflow-hidden rounded-2xl border bg-white shadow-sm">
            <h2 class="border-b px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
                Semaine à venir — projection
            </h2>

            @forelse (array_slice($projection, 0, 15) as $ligne)
            <div class="flex items-center justify-between gap-3 border-b px-5 py-3 last:border-0">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">
                        {{ $ligne['zone_name'] }} — {{ $ligne['trade_name'] }}
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ $ligne['weeks_observed'] }} semaine(s) observée(s),
                        {{ $ligne['weekly_average'] }} par semaine en moyenne
                    </p>
                </div>

                <div class="shrink-0 text-right">
                    @if ($ligne['has_enough_history'])
                    <p class="text-sm font-bold tabular-nums text-slate-900">
                        {{ $ligne['next_week_forecast'] }}
                    </p>
                    {{-- L'intervalle est le chiffre honnête : une projection nue ferait prendre une
                         extrapolation pour une mesure. --}}
                    <p class="text-xs text-slate-500">
                        entre {{ $ligne['forecast_low'] }} et {{ $ligne['forecast_high'] }}
                    </p>
                    @else
                    <p class="text-xs text-slate-400">Historique insuffisant</p>
                    @endif
                </div>
            </div>
            @empty
            <p class="px-5 py-8 text-center text-sm text-slate-500">
                Pas assez d'historique pour projeter quoi que ce soit d'utile.
            </p>
            @endforelse
        </div>

        {{-- Le rattrapage --}}
        <div class="overflow-hidden rounded-2xl border bg-white shadow-sm">
            <h2 class="border-b px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
                Recherches sans réponse — à rattraper
            </h2>

            <div class="border-b bg-slate-50 px-5 py-3">
                <div class="grid gap-3 sm:grid-cols-3">
                    <label for="recovery-message" class="block sm:col-span-2">
                        <span class="mb-1 block text-xs font-semibold text-slate-600">Message au client</span>
                        <input id="recovery-message" type="text" wire:model="messageAuClient"
                            placeholder="Nous cherchons encore quelqu'un pour vous."
                            class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    </label>

                    <label for="recovery-geste" class="block">
                        <span class="mb-1 block text-xs font-semibold text-slate-600">Geste (%)</span>
                        <input id="recovery-geste" type="number" min="1" max="50" wire:model="pourcentageDuGeste"
                            class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    </label>
                </div>
            </div>

            @forelse ($echecs as $echec)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-3 last:border-0">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">
                        {{ $echec->booking?->booking_reference ?? 'Recherche #'.$echec->id }}
                        @if (($diagnostics[$echec->id] ?? null) === 'no_provider_found')
                        {{-- Deux diagnostics, deux actions opposées : recruter dans la zone, ou
                             comprendre pourquoi la course est refusée. --}}
                        <span class="ml-2 rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-bold text-rose-700">
                            Personne trouvé
                        </span>
                        @else
                        <span class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">
                            Tous ont refusé
                        </span>
                        @endif
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ $echec->booking?->clientUser?->name ?? 'Client inconnu' }}
                        · {{ $echec->created_at?->diffForHumans() }}
                        @if (data_get($echec->metadata, 'gesture_code'))
                        · geste émis : {{ data_get($echec->metadata, 'gesture_code') }}
                        @endif
                    </p>
                </div>

                <div class="flex shrink-0 flex-wrap gap-2">
                    <button type="button" wire:click="relancer({{ $echec->id }})"
                        class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                        Relancer
                    </button>
                    <button type="button" wire:click="contacter({{ $echec->id }})"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Contacter
                    </button>
                    <button type="button" wire:click="offrirUnGeste({{ $echec->id }})"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Geste commercial
                    </button>
                </div>
            </div>
            @empty
            <p class="px-5 py-8 text-center text-sm text-slate-500">
                Aucune recherche sans réponse sur la période. C'est la meilleure nouvelle de cet écran.
            </p>
            @endforelse
        </div>
    </div>
</div>
