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
