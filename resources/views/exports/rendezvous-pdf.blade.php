<h2 style="text-align: center;">Liste des Rendez-vous</h2>
<table width="100%" border="1" cellspacing="0" cellpadding="5">
    <thead>
        <tr>
            <th>Date</th>
            <th>Heure</th>
            <th>Client</th>
            <th>Employé</th>
            <th>Statut</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rdvs as $rdv)
            <tr>
                <td>{{ $rdv->date }}</td>
                <td>{{ $rdv->heure }}</td>
                <td>{{ $rdv->client->name ?? '-' }}</td>
                <td>{{ $rdv->employe->name ?? '-' }}</td>
                <td>{{ ucfirst($rdv->status) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
