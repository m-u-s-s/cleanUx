    <div class="grid grid-cols-2 xl:grid-cols-5 gap-4">
        <x-kpi-card title="Pays" :value="$countryStats['total']" tone="slate" icon="🌍" />
        <x-kpi-card title="Actifs" :value="$countryStats['active']" tone="green" icon="✅" />
        <x-kpi-card title="Inactifs" :value="$countryStats['inactive']" tone="rose" icon="⏸️" />
        <x-kpi-card title="Codes postaux" :value="$countryStats['postal_codes']" tone="blue" icon="📮" />
        <x-kpi-card title="Zones de service" :value="$countryStats['service_zones']" tone="amber" icon="🧭" />
    </div>
