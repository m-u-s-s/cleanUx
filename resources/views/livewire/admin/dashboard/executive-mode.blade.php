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
