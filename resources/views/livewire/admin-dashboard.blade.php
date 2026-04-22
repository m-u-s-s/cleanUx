<div class="space-y-6">
    @php
        $filtreZone = $filtreZone
            ?? data_get(auth()->user(), 'managed_service_zone_id')
            ?? data_get(auth()->user(), 'primary_service_zone_id');

        $adminScopeId = ($zoneScopeLocked ?? false) && filled($filtreZone)
            ? (int) $filtreZone
            : null;
    @endphp

    @include('livewire.admin.dashboard.shell')
    @include('livewire.admin.dashboard.kpis')
    @include('livewire.admin.dashboard.premium-overview')
    @include('livewire.admin.dashboard.alert-panels')
    @include('livewire.admin.dashboard.export-feedbacks')

    <livewire:admin.feedback-stats :scope-id="$adminScopeId" />

    @include('livewire.admin.dashboard.operations-overview')
    @include('livewire.admin.dashboard.quality-panel')
    @include('livewire.admin.dashboard.analytics-panel')
    @include('livewire.admin.dashboard.activity-log-panel')
    @include('livewire.admin.dashboard.employee-limits')
    @include('livewire.admin.dashboard.charts-and-calendar')
    @include('livewire.admin.dashboard.embedded-modules', ['adminScopeId' => $adminScopeId])
    @include('livewire.admin.dashboard.scripts')
</div>
