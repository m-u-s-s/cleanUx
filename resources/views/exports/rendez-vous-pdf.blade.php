<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Export rendez-vous</title>
</head>
<body>
    <h1>Export rendez-vous</h1>

    <table width="100%" border="1" cellspacing="0" cellpadding="6">
        <thead>
            <tr>
                <th>ID</th>
                <th>Client</th>
                <th>Employé</th>
                <th>Zone</th>
                <th>Date</th>
                <th>Heure</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $rdv)
                <tr>
                    <td>{{ $rdv->id }}</td>
                    <td>{{ $rdv->client?->name }}</td>
                    <td>{{ $rdv->employe?->name }}</td>
                    <td>{{ $rdv->serviceZone?->name }}</td>
                    <td>{{ $rdv->date }}</td>
                    <td>{{ $rdv->heure }}</td>
                    <td>{{ $rdv->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>