@if($visibleDashboardSections['operations'] ?? true)
    <livewire:admin.admin-alerts-center />
@endif

@if($visibleDashboardSections['analytics'] ?? true)
    {{-- L'apercu monte ici doublait la courbe mensuelle voisine, avec d'autres chiffres :
         ses totaux sont passes en section « Plateforme », son CA dans le graphique mensuel. --}}
    @if(! $compactMode)
        <livewire:admin.employee-performance />
    @endif
@endif
