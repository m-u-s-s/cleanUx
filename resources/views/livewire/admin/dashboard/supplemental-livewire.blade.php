@if($visibleDashboardSections['operations'] ?? true)
    <livewire:admin.admin-alerts-center />
@endif

@if($visibleDashboardSections['analytics'] ?? true)
    <livewire:admin.admin-analytics-dashboard />

    @if(! $compactMode)
        <livewire:admin.employee-performance />
    @endif
@endif
