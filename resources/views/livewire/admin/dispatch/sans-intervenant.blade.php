{{--
    REPRIS D'« IA DISPATCH ». L'ancienne page ecrivait `employe_id` en direct et confirmait la
    reservation : ni offre, ni MissionAssignment, ni garde KYC ou controle facial. Ici l'action
    passe par `DispatchEngine::imposerDOffice()`, qui repasse les deux gardes de la voie courtoise.
--}}
<section class="rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900">
    <div class="border-b border-slate-100 p-5 dark:border-white/5">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Missions sans intervenant</h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
            Uniquement celles sur lesquelles l’imposition peut agir : planifiées, sans lead, hors
            mode immédiat. Imposer <strong>désigne sans l’accord</strong> du prestataire — l’offre
            reste la voie courtoise.
        </p>
    </div>

    <div class="brio-table-cadre">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="p-4">Réservation</th>
                    <th>Client</th>
                    <th>Quand</th>
                    <th>Ville</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                @forelse ($sansIntervenant as $mission)
                    <tr wire:key="sans-intervenant-{{ $mission->id }}">
                        <td class="p-4 font-medium text-slate-900 dark:text-white">
                            {{ $mission->booking?->booking_reference ?? '—' }}
                        </td>
                        <td class="text-slate-600 dark:text-slate-300">
                            {{ $mission->booking?->client?->name ?? '—' }}
                        </td>
                        <td class="text-slate-600 dark:text-slate-300">
                            {{ $mission->booking?->date }} {{ $mission->booking?->heure }}
                        </td>
                        <td class="text-slate-600 dark:text-slate-300">{{ $mission->booking?->city ?? '—' }}</td>
                        <td class="p-4 text-right">
                            <button type="button" wire:click="imposer({{ $mission->id }})"
                                wire:confirm="Imposer cette mission d’office ? Le prestataire n’aura pas donné son accord."
                                class="text-sm font-semibold text-rose-600 hover:underline">
                                Imposer d’office
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-sm text-slate-500">
                            Aucune mission planifiée sans intervenant.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-4">{{ $sansIntervenant->links() }}</div>
</section>
