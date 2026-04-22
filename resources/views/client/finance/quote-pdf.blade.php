<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Devis {{ $quote->quote_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 14px; }
        .muted { color: #475569; }
        .card { border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 8px; }
    </style>
</head>
<body>
    <h1>Devis {{ $quote->quote_number }}</h1>
    <p class="muted">Émis le {{ $quote->formatDocumentDate($quote->issued_at) }}</p>

    <div class="card">
        <div class="row"><strong>Client</strong><span>{{ $quote->client?->name ?? $quote->organizationAccount?->name ?? '—' }}</span></div>
        <div class="row"><strong>Service</strong><span>{{ $quote->rendezVous?->service_display_name ?? data_get($quote->snapshot, 'service_name', '—') }}</span></div>
        <div class="row"><strong>Zone</strong><span>{{ $quote->rendezVous?->serviceZone?->name ?? data_get($quote->snapshot, 'zone_name', '—') }}</span></div>
        <div class="row"><strong>Adresse</strong><span>{{ $quote->rendezVous?->location_display ?? data_get($quote->snapshot, 'location_display', '—') }}</span></div>
        <div class="row"><strong>Pays</strong><span>{{ data_get($quote->snapshot, 'country_name', '—') }}</span></div>
        <div class="row"><strong>Validité</strong><span>{{ $quote->formatDocumentDate($quote->valid_until) }}</span></div>
    </div>

    <div class="card">
        <div class="row"><span>Sous-total</span><strong>{{ $quote->formatDocumentMoney($quote->subtotal) }}</strong></div>
        <div class="row"><span>{{ $quote->documentTaxLabel() }}</span><strong>{{ $quote->formatDocumentMoney($quote->tax_amount) }}</strong></div>
        <div class="row"><span>Total</span><strong>{{ $quote->formatDocumentMoney($quote->total_amount) }}</strong></div>
    </div>
</body>
</html>
