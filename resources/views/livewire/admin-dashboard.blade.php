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
        @include('livewire.admin.dashboard.cockpit-hero')
        @include('livewire.admin.dashboard.base-controls')
        @include('livewire.admin.dashboard.priority-kpis-section')

        @if($executiveMode)
            @include('livewire.admin.dashboard.executive-mode')
        @else
            @include('livewire.admin.dashboard.operations-section')
            @include('livewire.admin.dashboard.analytics-section')
            @include('livewire.admin.dashboard.premium-section')
            @include('livewire.admin.dashboard.charts-section')
            @include('livewire.admin.dashboard.tools-section')
            @include('livewire.admin.dashboard.modules-section')
        @endif

        @include('livewire.admin.dashboard.scripts')
        @include('livewire.admin.dashboard.supplemental-livewire')
    </div>
</div>
