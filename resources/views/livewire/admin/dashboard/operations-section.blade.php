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
