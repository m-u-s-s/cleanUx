<div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
    <x-kpi-card title="CA estimé HTVA" :value="locale_currency($kpis['total_htva'])" tone="blue" icon="💼" />
    <x-kpi-card title="Entreprise HTVA" :value="locale_currency($kpis['entreprise_htva'])" tone="amber" icon="🏢" />
    <x-kpi-card title="À facturer HTVA" :value="locale_currency($kpis['to_invoice_htva'])" tone="slate" icon="🧾" />
    <x-kpi-card title="Marge estimée" :value="locale_currency($kpis['margin_estimate'])" tone="green" icon="📈" />
    <x-kpi-card title="Solde à encaisser" :value="locale_currency($kpis['outstanding_balance'])" tone="rose" icon="⏱️" />
    <x-kpi-card title="Factures en retard" :value="$kpis['overdue_count']" :hint="locale_currency($kpis['overdue_balance'])" tone="red" icon="🚨" />
</div>
