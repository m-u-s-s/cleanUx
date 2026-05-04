    <div class="rounded-2xl border bg-white shadow-sm overflow-hidden">
        <div class="border-b p-5">
            <h3 class="font-semibold text-slate-900">Factures B2B générées</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Facture</th>
                        <th class="px-4 py-3 text-left">Entreprise</th>
                        <th class="px-4 py-3 text-left">Période</th>
                        <th class="px-4 py-3 text-left">Total</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                        <th class="px-4 py-3 text-left">Sites</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    @forelse($invoices as $invoice)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                {{ $invoice->invoice_number }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $invoice->organizationAccount?->name ?? '—' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $invoice->billing_period_start?->format('d/m/Y') }}
                                →
                                {{ $invoice->billing_period_end?->format('d/m/Y') }}
                            </td>

                            <td class="px-4 py-3 font-semibold text-slate-900">
                                {{ number_format((float) $invoice->total_amount, 2, ',', ' ') }}
                                {{ $invoice->currency }}
                            </td>

                            <td class="px-4 py-3">
                                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                                    {{ $invoice->status }}
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                @foreach((array) $invoice->site_breakdown as $site)
                                    <div class="text-xs text-slate-600">
                                        {{ $site['site'] ?? '—' }} :
                                        {{ $site['count'] ?? 0 }} RDV —
                                        {{ number_format((float) ($site['subtotal'] ?? 0), 2, ',', ' ') }} €
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">
                                Aucune facture B2B générée.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">
            {{ $invoices->links() }}
        </div>

        
    </div>
