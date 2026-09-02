{{--
    LE SCORING NE SE REGARDE PLUS, IL SE DÉCIDE.

    L'écran classait les candidats et s'arrêtait là : pour affecter, l'administrateur devait
    fermer, puis lancer le dispatch automatique — qui reprend le PREMIER. Voir un second à deux
    points du premier, mieux placé ce jour-là, et ne pas pouvoir le choisir, c'était donner une
    information sans le geste qu'elle appelle.
--}}
@if($dispatchPreviewRdvId)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
        <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl space-y-4">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Scoring dispatch</h3>
                    <p class="text-sm text-slate-500">
                        Prestataires du métier demandé, libres sur ce créneau.
                        Choisissez celui que vous voulez affecter.
                    </p>
                </div>

                <button type="button" wire:click="closeDispatchPreview"
                    class="brio-btn-ligne" aria-label="Fermer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7"
                        stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="max-h-[60vh] space-y-2 overflow-y-auto">
                @forelse($dispatchPreview as $rang => $row)
                    <div class="flex items-center justify-between gap-3 rounded-xl border p-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-900">
                                <span class="mr-1 text-xs text-slate-400">{{ $rang + 1 }}.</span>
                                {{ $row['name'] }}
                            </p>
                            <p class="text-xs text-emerald-600">
                                Libre sur ce créneau
                                @if (($row['distance_km'] ?? null) !== null)
                                    <span class="text-slate-400">· {{ $row['distance_km'] }} km</span>
                                @endif
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            <div class="text-right">
                                <p class="text-xl font-bold text-indigo-700">{{ $row['score'] }}</p>
                                <p class="text-xs text-slate-500">score</p>
                            </div>

                            <button
                                type="button"
                                wire:click="choisirPrestataire({{ $dispatchPreviewRdvId }}, {{ $row['employee_id'] }})"
                                wire:confirm="Affecter ce rendez-vous à {{ $row['name'] }} ? Le rendez-vous passe en confirmé."
                                class="brio-btn-ligne brio-btn-ligne-accent gap-1.5 font-semibold"
                            >
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                Choisir
                            </button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">
                        Aucun prestataire du métier demandé n’est libre sur ce créneau.
                        {{-- Les deux causes se ressemblent tant qu’on ne les nomme pas :
                             personne du bon métier, ou tout le monde déjà pris. --}}
                    </p>
                @endforelse
            </div>
        </div>
    </div>
@endif
