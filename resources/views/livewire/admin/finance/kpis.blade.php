<div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
    <x-kpi-card title="CA estimé HTVA" :value="'€ '.number_format($kpis['total_htva'], 2, ',', ' ')" tone="blue" icon="💼" />
    <x-kpi-card title="Entreprise HTVA" :value="'€ '.number_format($kpis['entreprise_htva'], 2, ',', ' ')" tone="amber" icon="🏢" />
    <x-kpi-card title="À facturer HTVA" :value="'€ '.number_format($kpis['to_invoice_htva'], 2, ',', ' ')" tone="slate" icon="🧾" />
    <x-kpi-card title="Marge estimée" :value="'€ '.number_format($kpis['margin_estimate'], 2, ',', ' ')" tone="green" icon="📈" />
    <x-kpi-card title="Solde à encaisser" :value="'€ '.number_format($kpis['outstanding_balance'], 2, ',', ' ')" tone="rose" icon="⏱️" />
    <x-kpi-card title="Factures en retard" :value="$kpis['overdue_count']" :hint="'€ '.number_format($kpis['overdue_balance'], 2, ',', ' ')" tone="red" icon="🚨" />
</div>
