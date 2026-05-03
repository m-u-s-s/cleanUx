<section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <x-kpi-card :title="__('Devis')" :value="$financeSummary['quotes_count']" tone="sky" icon="🧾" />
    <x-kpi-card :title="__('À valider')" :value="$financeSummary['quotes_pending']" tone="amber" icon="⏳" />
    <x-kpi-card :title="__('Factures')" :value="$financeSummary['invoices_count']" tone="slate" icon="📄" />
    <x-kpi-card :title="__('En retard')" :value="$financeSummary['overdue_count']" tone="rose" icon="⚠️" />
    <x-kpi-card
        :title="__('Reste à payer')"
        :value="number_format((float) $financeSummary['outstanding_total'], 2, ',', ' ') . ' ' . ($financeSummary['currency_symbol'] ?? '€')"
        tone="emerald"
        icon="💳"
    />
</section>
