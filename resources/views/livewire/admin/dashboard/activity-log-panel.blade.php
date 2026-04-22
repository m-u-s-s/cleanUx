<div class="cu-card p-5">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-800">🕓 Journal d’activité admin</h3>
            <p class="text-sm text-gray-500">Historique récent des actions sensibles réalisées dans l’outil.</p>
        </div>
    </div>

    <div class="space-y-3">
        @forelse($recentActivityLogs as $log)
            <div class="rounded-lg border bg-gray-50 p-4">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="font-semibold text-gray-800">
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
                            @endphp

                            {{ $actionLabel }}
                        </p>
                        <p class="text-sm text-gray-600">Par {{ $log->user->name ?? 'Système automatique' }} • {{ $log->created_at->diffForHumans() }}</p>
                    </div>

                    <div class="text-xs text-gray-500">
                        @if($log->target_id)
                            Cible #{{ $log->target_id }}
                        @endif
                    </div>
                </div>

                @if(!empty($log->meta))
                    <div class="mt-3 grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                        @foreach($log->meta as $key => $value)
                            <div class="rounded border bg-white p-2">
                                <span class="font-medium text-gray-700">{{ ucfirst(str_replace('_', ' ', $key)) }} :</span>
                                <span class="text-gray-600">{{ is_array($value) ? json_encode($value) : $value }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="text-sm italic text-gray-500">Aucune activité enregistrée pour le moment.</div>
        @endforelse
    </div>
</div>
