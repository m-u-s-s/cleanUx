{{-- AGENDA HEBDOMADAIRE --}}
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
            <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                        Agenda hebdomadaire
                    </p>

                    <h2 class="text-2xl font-black text-slate-900">
                        Vue semaine claire et compacte
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Visualise la charge par jour, les urgences, les missions sans employé et les rendez-vous principaux.
                    </p>
                </div>

                {{-- LA NAVIGATION DE SEMAINE, LA OU ON REGARDE LA SEMAINE.
                     Elle n'existait que dans le bloc de filtres, deux sections plus haut :
                     changer de semaine obligeait a remonter, puis a redescendre. --}}
                <div class="flex flex-shrink-0 items-center gap-2">
                    {{-- UN PAGINATEUR, PAS TROIS BOUTONS EN TOUTES LETTRES : « ← Semaine
                         précédente » et « Semaine suivante → » cote a cote poussaient
                         « Aujourd'hui » a la ligne et ecrasaient le sous-titre. Les fleches
                         gardent leur nom pour le lecteur d'ecran et l'infobulle. --}}
                    <div class="flex items-center gap-1 rounded-2xl bg-slate-100 p-1">
                        <button type="button" wire:click="semainePrecedente"
                            class="rounded-xl px-2.5 py-1.5 text-sm font-bold text-slate-600 transition hover:bg-white hover:text-slate-900 dark:hover:bg-white/10"
                            title="Semaine précédente">
                            ←<span class="sr-only">Semaine précédente</span>
                        </button>

                        <span class="whitespace-nowrap px-2 text-sm font-semibold tabular-nums text-slate-700">
                            {{ $weekStart->translatedFormat('d M') }} → {{ $weekEnd->translatedFormat('d M Y') }}
                        </span>

                        <button type="button" wire:click="semaineSuivante"
                            class="rounded-xl px-2.5 py-1.5 text-sm font-bold text-slate-600 transition hover:bg-white hover:text-slate-900 dark:hover:bg-white/10"
                            title="Semaine suivante">
                            →<span class="sr-only">Semaine suivante</span>
                        </button>
                    </div>

                    <button type="button" wire:click="allerAujourdHui" class="brio-btn-secondary whitespace-nowrap px-3 py-2 text-xs">
                        Aujourd’hui
                    </button>
                </div>
            </div>

            <livewire:admin.agenda-hebdomadaire
                :semaine="$semaine"
                :employe-id="$filtreEmploye"
                :status="$filtreStatus"
                :priorite="$filtrePriorite"
                :recherche="$recherche"
                :focus-date="$focusDate->toDateString()"
                :key="'agenda-'.$semaine.'-'.$filtreEmploye.'-'.$filtreStatus.'-'.$filtrePriorite.'-'.md5($recherche.$focusDate->toDateString())"
            />
        </section>
