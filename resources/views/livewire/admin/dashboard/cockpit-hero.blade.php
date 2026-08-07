<div class="ui-page-header">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="ui-page-eyebrow">Cockpit administrateur</p>
            <h1 class="ui-page-title">Pilotage global Brio</h1>
            <p class="ui-page-subtitle">
                Rendez-vous, missions, zones, alertes, qualité, finance et activité terrain.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if(Route::has('admin.planning'))
                <a href="{{ route('admin.planning') }}" class="cu-btn-primary inline-flex items-center gap-2">
                    <x-ui.icon name="calendar" class="w-4 h-4" />
                    <span>Planning</span>
                </a>
            @endif

            @if(Route::has('admin.missions'))
                <a href="{{ route('admin.missions') }}" class="cu-btn-secondary inline-flex items-center gap-2">
                    <x-ui.icon name="briefcase" class="w-4 h-4" />
                    <span>Missions</span>
                </a>
            @endif

            @if(Route::has('admin.finance'))
                <a href="{{ route('admin.finance') }}" class="cu-btn-secondary inline-flex items-center gap-2">
                    <x-ui.icon name="currency-euro" class="w-4 h-4" />
                    <span>Finance</span>
                </a>
            @endif
        </div>
    </div>
</div>
