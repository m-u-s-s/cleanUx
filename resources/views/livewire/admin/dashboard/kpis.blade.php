<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
    <x-kpi-card title="En attente" :value="$adminKpis['en_attente']" tone="amber" icon="⏳" />
    <x-kpi-card title="Urgences vieillissantes" :value="$adminKpis['urgentes_vieilles']" tone="red" icon="🚨" />
    <x-kpi-card title="Missions longues" :value="$adminKpis['missions_longues']" tone="orange" icon="🕒" />
    <x-kpi-card title="Employés surchargés" :value="$adminKpis['employes_surcharges']" tone="rose" icon="👥" />
    <x-kpi-card title="Missions du jour" :value="$adminKpis['missions_du_jour']" tone="blue" icon="📅" />
    <x-kpi-card title="Terminées ce mois" :value="$adminKpis['missions_terminees_mois']" tone="green" icon="✅" />
</div>

<div class="grid grid-cols-1 gap-4 md:grid-cols-3">
    <x-kpi-card title="Clients Premium actifs" :value="$premiumClientsCount" tone="amber" icon="★" />
    <x-kpi-card title="Clients Standard" :value="$standardClientsCount" tone="slate" icon="👤" />
    <x-kpi-card title="Abonnements actifs" :value="$activeSubscriptionsCount" tone="green" icon="💳" />
</div>
