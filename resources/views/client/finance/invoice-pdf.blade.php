<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 14px; }
        .muted { color: #475569; }
        .card { border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 8px; }
    </style>
</head>
<body>
    <h1>Facture {{ $invoice->invoice_number }}</h1>
    <p class="muted">Émise le {{ optional($invoice->issued_at)->format('d/m/Y') ?: '—' }}</p>

    <div class="card">
        <div class="row"><strong>Client</strong><span>{{ $invoice->client?->name ?? $invoice->organizationAccount?->name ?? '—' }}</span></div>
        <div class="row"><strong>Service</strong><span>{{ $invoice->rendezVous?->service_display_name ?? '—' }}</span></div>
        <div class="row"><strong>Zone</strong><span>{{ $invoice->rendezVous?->serviceZone?->name ?? '—' }}</span></div>
        <div class="row"><strong>Adresse</strong><span>{{ $invoice->rendezVous?->location_display ?? '—' }}</span></div>
        <div class="row"><strong>Échéance</strong><span>{{ optional($invoice->due_at)->format('d/m/Y') ?: '—' }}</span></div>
    </div>

    <div class="card">
        <div class="row"><span>Sous-total</span><strong>{{ number_format((float) $invoice->subtotal, 2, ',', ' ') }} €</strong></div>
        <div class="row"><span>TVA</span><strong>{{ number_format((float) $invoice->tax_amount, 2, ',', ' ') }} €</strong></div>
        <div class="row"><span>Total</span><strong>{{ number_format((float) $invoice->total_amount, 2, ',', ' ') }} €</strong></div>
        <div class="row"><span>Reste à payer</span><strong>{{ number_format((float) $invoice->balance_due, 2, ',', ' ') }} €</strong></div>
    </div>
</body>
</html>
