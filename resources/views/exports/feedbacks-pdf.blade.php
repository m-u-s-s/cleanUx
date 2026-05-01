<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Export feedbacks</title>
</head>
<body>
    <h1>Export feedbacks</h1>

    <table width="100%" border="1" cellspacing="0" cellpadding="6">
        <thead>
            <tr>
                <th>ID</th>
                <th>Client</th>
                <th>Note</th>
                <th>Commentaire</th>
                <th>Zone</th>
            </tr>
        </thead>
        <tbody>
            @foreach($feedbacks as $feedback)
                <tr>
                    <td>{{ $feedback->id }}</td>
                    <td>{{ $feedback->client?->name }}</td>
                    <td>{{ $feedback->note }}</td>
                    <td>{{ $feedback->commentaire }}</td>
                    <td>{{ $feedback->rendezVous?->serviceZone?->name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>