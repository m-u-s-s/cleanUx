<h2>Liste des rendez-vous</h2>
<table width="100%" border="1" cellpadding="4" cellspacing="0">
    <thead>
        <tr>
            <th>Date</th>
            <th>Heure</th>
            <th>Client</th>
            <th>Employé</th>
            <th>Service</th>
            <th>Zone</th>
            <th>Code postal</th>
            <th>Statut</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data as $rdv)
            <tr>
                <td>{{ optional($rdv->date)->format('d/m/Y') }}</td>
                <td>{{ substr((string) $rdv->heure, 0, 5) }}</td>
                <td>{{ $rdv->client->name ?? '—' }}</td>
                <td>{{ $rdv->employe->name ?? '—' }}</td>
                <td>{{ $rdv->service_display_name }}</td>
                <td>{{ $rdv->serviceZone?->name ?? '—' }}</td>
                <td>{{ $rdv->postal_code_display }}</td>
                <td>{{ $rdv->status }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
