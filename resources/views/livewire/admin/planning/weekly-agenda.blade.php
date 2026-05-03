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

                <div class="rounded-2xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">
                    {{ $weekStart->translatedFormat('d M') }} → {{ $weekEnd->translatedFormat('d M Y') }}
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
