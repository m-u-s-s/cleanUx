<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Modules avancés</p>
            <h3 class="text-xl font-black text-slate-900">Outils intégrés au dashboard</h3>
            <p class="text-sm text-slate-500">
                Accès rapide aux modules de qualité RH, feedbacks, utilisateurs et planning.
            </p>
        </div>

        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
            Administration
        </span>
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="mb-4">
                <h4 class="font-black text-slate-900">Qualité RH</h4>
                <p class="text-sm text-slate-500">
                    Suivi qualité des employés et indicateurs RH.
                </p>
            </div>

            <livewire:admin.rh-quality-scores />
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="mb-4">
                <h4 class="font-black text-slate-900">Feedbacks clients</h4>
                <p class="text-sm text-slate-500">
                    Vue globale des retours clients dans le scope admin.
                </p>
            </div>

            <livewire:admin-feedbacks :scope-id="$adminScopeId" />
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="mb-4">
                <h4 class="font-black text-slate-900">Récapitulatif des accès</h4>
                <p class="text-sm text-slate-500">
                    Synthèse des droits et accès de l’espace admin.
                </p>
            </div>

            <x-admin.recapitulatif-acces />
        </div>

        @if(! $zoneScopeLocked)
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div class="mb-4">
                    <h4 class="font-black text-slate-900">Gestion utilisateurs</h4>
                    <p class="text-sm text-slate-500">
                        Gestion rapide des rôles, comptes et accès.
                    </p>
                </div>

                <livewire:admin.gestion-utilisateurs />
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div class="mb-4">
                    <h4 class="font-black text-slate-900">Agenda hebdomadaire</h4>
                    <p class="text-sm text-slate-500">
                        Vue semaine pour suivre les disponibilités et missions.
                    </p>
                </div>

                <livewire:admin.agenda-hebdomadaire />
            </div>
        @else
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <p class="font-black text-amber-800">Scope zone activé</p>
                <p class="mt-1 text-sm text-amber-700">
                    Certains modules globaux sont masqués car cet administrateur est limité à une zone.
                </p>
            </div>
        @endif
    </div>
</div>