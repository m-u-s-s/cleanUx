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
    <p class="muted">Émis le {{ optional($quote->issued_at)->format('d/m/Y') ?: '—' }}</p>

    <div class="card">
        <div class="row"><strong>Client</strong><span>{{ $quote->client?->name ?? $quote->organizationAccount?->name ?? '—' }}</span></div>
        <div class="row"><strong>Service</strong><span>{{ $quote->rendezVous?->service_display_name ?? '—' }}</span></div>
        <div class="row"><strong>Zone</strong><span>{{ $quote->rendezVous?->serviceZone?->name ?? '—' }}</span></div>
        <div class="row"><strong>Adresse</strong><span>{{ $quote->rendezVous?->location_display ?? '—' }}</span></div>
        <div class="row"><strong>Validité</strong><span>{{ optional($quote->valid_until)->format('d/m/Y') ?: '—' }}</span></div>
    </div>

    <div class="card">
        <div class="row"><span>Sous-total</span><strong>{{ number_format((float) $quote->subtotal, 2, ',', ' ') }} €</strong></div>
        <div class="row"><span>TVA</span><strong>{{ number_format((float) $quote->tax_amount, 2, ',', ' ') }} €</strong></div>
        <div class="row"><span>Total</span><strong>{{ number_format((float) $quote->total_amount, 2, ',', ' ') }} €</strong></div>
    </div>
</body>
</html>
