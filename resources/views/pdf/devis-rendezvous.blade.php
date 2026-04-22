<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 22px; margin-bottom: 6px; }
        .muted { color: #6b7280; }
        .box { border: 1px solid #e5e7eb; padding: 12px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f8fafc; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Devis {{ $quoteNumber }}</h1>
    <div class="muted">CleanUx · généré le {{ now()->format('d/m/Y H:i') }}</div>

    <div class="box">
        <strong>Client</strong><br>
        {{ $rdv->organizationAccount?->legal_name ?: $rdv->organizationAccount?->name ?: $rdv->client?->name }}<br>
        {{ $rdv->adresse }}<br>
        {{ $rdv->postal_code_display }} {{ $rdv->ville }}<br>
        TVA : {{ $rdv->organizationAccount?->tva_number ?: '—' }}
    </div>

    <div class="box">
        <strong>Mission</strong><br>
        Référence réservation : {{ $rdv->booking_reference ?: 'RDV-'.$rdv->id }}<br>
        Date : {{ optional($rdv->date)->format('d/m/Y') }} à {{ substr((string) $rdv->heure, 0, 5) }}<br>
        Service : {{ data_get($rdv->pricing_snapshot, 'service_name', $rdv->service_display_name) }}<br>
        Zone : {{ $rdv->serviceZone?->name ?: '—' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Montant HTVA</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ data_get($rdv->pricing_snapshot, 'service_name', $rdv->service_display_name) }} · {{ $rdv->location_display }}</td>
                <td class="right">€ {{ number_format($amountHtva, 2, ',', ' ') }}</td>
            </tr>
            <tr>
                <td class="right"><strong>TVA {{ number_format($taxRate, 0) }}%</strong></td>
                <td class="right">€ {{ number_format($amountTva, 2, ',', ' ') }}</td>
            </tr>
            <tr>
                <td class="right"><strong>Total TVAC</strong></td>
                <td class="right"><strong>€ {{ number_format($amountTvac, 2, ',', ' ') }}</strong></td>
            </tr>
        </tbody>
    </table>

    <p class="muted" style="margin-top: 18px;">Validité du devis : 15 jours. Sous réserve de validation finale si la mission exige une validation manuelle.</p>
</body>
</html>
