    <div class="grid grid-cols-2 xl:grid-cols-5 gap-4">
        <x-kpi-card title="Total zones" :value="$zoneStats['total']" tone="slate" icon="🧭" />
        <x-kpi-card title="Actives" :value="$zoneStats['active']" tone="green" icon="✅" />
        <x-kpi-card title="En pause" :value="$zoneStats['paused']" tone="amber" icon="⏸️" />
        <x-kpi-card title="Réservables" :value="$zoneStats['bookable']" tone="blue" icon="📅" />
        <x-kpi-card title="Visibles" :value="$zoneStats['visible']" tone="slate" icon="👁️" />
    </div>
