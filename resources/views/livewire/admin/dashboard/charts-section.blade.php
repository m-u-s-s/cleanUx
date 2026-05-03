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
