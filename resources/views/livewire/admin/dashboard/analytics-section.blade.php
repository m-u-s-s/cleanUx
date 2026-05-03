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
