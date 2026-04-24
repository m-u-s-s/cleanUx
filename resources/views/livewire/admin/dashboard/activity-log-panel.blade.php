<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-5 flex items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Audit</p>
            <h3 class="text-xl font-black text-slate-900">Journal d’activité admin</h3>
            <p class="text-sm text-slate-500">
                Dernières actions sensibles ou automatiques.
            </p>
        </div>

        <a href="{{ route('admin.audit.logs') }}"
           class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-700">
            Voir tout
        </a>
    </div>

    <div class="space-y-3">
        @forelse($recentActivityLogs as $log)
            @php
                $actionLabel = match($log->action) {
                    'mission_replanifiee' => 'Mission replanifiée',
                    'mission_statut_modifie' => 'Statut de mission modifié',
                    'mission_terminee_avec_rapport' => 'Mission terminée avec rapport',
                    'rdv_modifie_par_client' => 'Rendez-vous modifié par le client',
                    'rdv_annule_par_client' => 'Rendez-vous annulé par le client',
                    'feedback_repondu_par_admin' => 'Réponse admin à un feedback',
                    'export_rendez_vous' => 'Export des rendez-vous',
                    'export_feedbacks' => 'Export des feedbacks',
                    'import_csv_execute' => 'Import CSV exécuté',
                    'import_csv_avec_erreurs' => 'Import CSV avec erreurs',
                    'rappel_24h_envoye' => 'Rappel 24h envoyé',
                    'rappel_2h_envoye' => 'Rappel 2h envoyé',
                    'demande_feedback_envoyee' => 'Demande de feedback envoyée',
                    'alerte_urgence_envoyee' => 'Alerte urgence envoyée',
                    'alerte_depassement_durees' => 'Alerte sur dépassements de durée',
                    'alerte_taux_feedback_faible' => 'Alerte taux de feedback faible',
                    'suggestion_reaffectation_auto' => 'Suggestion automatique de réaffectation',
                    default => ucfirst(str_replace('_', ' ', $log->action)),
                };

                $isCritical = str_contains($log->action, 'export')
                    || str_contains($log->action, 'delete')
                    || str_contains($log->action, 'supprime')
                    || str_contains($log->action, 'security')
                    || str_contains($log->action, 'incident');
            @endphp

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-black text-slate-900">
                            {{ $isCritical ? '🔐' : '🕓' }} {{ $actionLabel }}
                        </p>

                        <p class="mt-1 text-sm text-slate-500">
                            Par {{ $log->user->name ?? 'Système automatique' }}
                            · {{ $log->created_at?->diffForHumans() }}
                        </p>
                    </div>

                    @if($log->target_id)
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-slate-600 ring-1 ring-slate-200">
                            #{{ $log->target_id }}
                        </span>
                    @endif
                </div>

                @if(!empty($log->meta))
                    <details class="mt-3">
                        <summary class="cursor-pointer text-xs font-bold text-blue-700">
                            Voir les détails
                        </summary>

                        <div class="mt-3 grid grid-cols-1 gap-2 text-xs">
                            @foreach($log->meta as $key => $value)
                                <div class="rounded-xl border bg-white p-3">
                                    <span class="font-black text-slate-700">
                                        {{ ucfirst(str_replace('_', ' ', $key)) }} :
                                    </span>

                                    <span class="text-slate-600">
                                        {{ is_array($value) ? json_encode($value) : $value }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>
        @empty
            <x-empty-state title="Aucune activité" message="Les actions récentes apparaîtront ici." icon="🕓" />
        @endforelse
    </div>
</div>