<div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-200 hover:shadow-md">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-lg font-black text-slate-900">
                    {{ $quote->quote_number }}
                </p>

                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $this->quoteStatusBadge((string) $quote->status) }}">
                    {{ ucfirst((string) $quote->status) }}
                </span>
            </div>

            <p class="mt-2 text-sm font-semibold text-slate-700">
                {{ $quote->rendezVous?->service_display_name ?? 'Service non précisé' }}
            </p>

            <p class="mt-1 text-sm text-slate-500">
                {{ $quote->rendezVous?->serviceZone?->name ?? 'Zone non précisée' }}
                @if($quote->rendezVous?->organizationSite)
                    · {{ $quote->rendezVous->organizationSite->name }}
                @endif
            </p>

            <p class="mt-1 text-sm text-slate-500">
                Émis le {{ optional($quote->issued_at)->format('d/m/Y') ?: '—' }}
                · Valable jusqu’au {{ optional($quote->valid_until)->format('d/m/Y') ?: '—' }}
            </p>

            @if($quote->invoice)
                <p class="mt-2 text-xs font-semibold text-emerald-700">
                    Facture liée : {{ $quote->invoice->invoice_number }}
                </p>
            @endif
        </div>

        <div class="flex flex-col items-start gap-3 lg:items-end">
            <p class="text-2xl font-black text-slate-900">
                {{ $quote->formatDocumentMoney($quote->total_amount) }}
            </p>

            <a href="{{ route('client.finance.quote.download', $quote) }}" class="cu-btn-secondary">
                📥 Télécharger
            </a>
        </div>
    </div>
</div>
