@if(($visibleDashboardSections['modules'] ?? false) && ! $compactMode)
    <section class="space-y-6">
        <details class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <summary class="cursor-pointer list-none">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                            Modules avancés
                        </p>
                        <h2 class="text-2xl font-black text-slate-900">
                            Ouvrir les modules intégrés
                        </h2>
                        <p class="text-sm text-slate-500">
                            Feedbacks détaillés, qualité RH, utilisateurs et agenda hebdomadaire.
                        </p>
                    </div>

                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                        Cliquer pour ouvrir
                    </span>
                </div>
            </summary>

            <div class="mt-6 space-y-6">
                <livewire:admin.feedback-stats :scope-id="$adminScopeId" />

                @include('livewire.admin.dashboard.embedded-modules', [
                    'adminScopeId' => $adminScopeId,
                ])
            </div>
        </details>
    </section>
@endif
