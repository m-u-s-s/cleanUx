<div class="min-h-screen bg-slate-50" @if($realtimeEnabled) wire:poll.10s="realtimeRefresh" @endif>
    @include('livewire.admin.dashboard.loading-overlay')
    @include('livewire.admin.dashboard.calendar-modal')
    @include('livewire.admin.dashboard.quick-actions')
    @include('livewire.admin.dashboard.mobile-actions')

    @php
    $filtreZone = $filtreZone
    ?? data_get(auth()->user(), 'managed_service_zone_id')
    ?? data_get(auth()->user(), 'primary_service_zone_id');

    $adminScopeId = ($zoneScopeLocked ?? false) && filled($filtreZone)
    ? (int) $filtreZone
    : null;
    @endphp

    <div class="mx-auto max-w-7xl space-y-8 px-4 pb-28 pt-6 sm:px-6 lg:px-8 lg:pb-8">

        {{-- Header --}}
        @include('livewire.admin.dashboard.shell')
        @include('livewire.admin.dashboard.filters')
        @include('livewire.admin.dashboard.realtime-indicator')
        @include('livewire.admin.dashboard.section-controls')

        {{-- KPIs principaux --}}
        <section class="space-y-4">
            @include('livewire.admin.dashboard.kpis')
        </section>
        @if($executiveMode)
        <section class="space-y-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                    Vue exécutive
                </p>
                <h2 class="text-2xl font-black text-slate-900">
                    Synthèse rapide de la plateforme
                </h2>
                <p class="text-sm text-slate-500">
                    Vue simplifiée pour suivre les priorités sans détails opérationnels.
                </p>
            </div>

            @include('livewire.admin.dashboard.executive-summary')
            @include('livewire.admin.dashboard.executive-actions')
            @include('livewire.admin.dashboard.alert-panels')
            @include('livewire.admin.dashboard.charts-and-calendar')

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                @include('livewire.admin.dashboard.activity-log-panel')
                @include('livewire.admin.dashboard.export-feedbacks')
            </div>
        </section>
        @else

        {{-- Opérations prioritaires --}}
        @if($visibleDashboardSections['operations'] ?? true)
        <section class="space-y-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
                    Priorité opérationnelle
                </p>
                <h2 class="text-2xl font-black text-slate-900">
                    Ce qui demande ton attention maintenant
                </h2>
                <p class="text-sm text-slate-500">
                    Urgences, interventions du jour, charge employés et missions à suivre.
                </p>
            </div>

            @include('livewire.admin.dashboard.operations-overview')
            @include('livewire.admin.dashboard.alert-panels')
        </section>
        @endif

        {{-- Analyse & qualité --}}
        @if($visibleDashboardSections['analytics'] ?? true)
        <section class="space-y-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-600">
                    Analyse & qualité
                </p>
                <h2 class="text-2xl font-black text-slate-900">
                    Performance, qualité et tendances
                </h2>
                <p class="text-sm text-slate-500">
                    Indicateurs métier, feedbacks, durées, services et qualité terrain.
                </p>
            </div>

            @include('livewire.admin.dashboard.analytics-panel')
            @if(! $compactMode)
            @include('livewire.admin.dashboard.quality-panel')
            @endif
        </section>
        @endif

        {{-- Premium & clients --}}
        @if(($visibleDashboardSections['premium'] ?? true) && ! $compactMode)
        <section class="space-y-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-amber-600">
                    Clients & premium
                </p>
                <h2 class="text-2xl font-black text-slate-900">
                    Suivi client et abonnements
                </h2>
                <p class="text-sm text-slate-500">
                    Clients premium, rendez-vous non assignés et accompagnement personnalisé.
                </p>
            </div>

            @if(! $compactMode)
            @include('livewire.admin.dashboard.premium-overview')
            @endif
        </section>
        @endif


        {{-- Graphiques & planning --}}
        @if($visibleDashboardSections['charts'] ?? true)
        <section class="space-y-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                    Planning visuel
                </p>
                <h2 class="text-2xl font-black text-slate-900">
                    Graphiques et calendrier global
                </h2>
                <p class="text-sm text-slate-500">
                    Vue visuelle de la charge, des rendez-vous et de l’évolution mensuelle.
                </p>
            </div>

            @include('livewire.admin.dashboard.charts-and-calendar')
        </section>
        @endif


        {{-- Outils rapides --}}
        @if($visibleDashboardSections['tools'] ?? true)
        <section class="space-y-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                    Outils rapides
                </p>
                <h2 class="text-2xl font-black text-slate-900">
                    Exports, limites et activité
                </h2>
                <p class="text-sm text-slate-500">
                    Actions administratives fréquentes et suivi des logs récents.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                @include('livewire.admin.dashboard.export-feedbacks')
                @include('livewire.admin.dashboard.activity-log-panel')
            </div>

            @if(! $compactMode)
            @include('livewire.admin.dashboard.employee-limits')
            @endif
        </section>
        @endif


        {{-- Modules lourds --}}
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
                    'adminScopeId' => $adminScopeId
                    ])
                </div>
            </details>
        </section>
        @endif
        @endif
        @include('livewire.admin.dashboard.scripts')
    </div>
</div>