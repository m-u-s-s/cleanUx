<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Export des feedbacks</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #222;
        }

        h1 {
            font-size: 18px;
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 6px;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #f3f4f6;
        }
    </style>
</head>
<body>
    <h1>Export des feedbacks</h1>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Client</th>
                <th>Employé</th>
                <th>Note</th>
                <th>Commentaire</th>
                <th>Réponse admin</th>
                <th>Créé le</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $feedback)
                <tr>
                    <td>{{ $feedback->id }}</td>
                    <td>{{ $feedback->client?->name ?? '—' }}</td>
                    <td>{{ $feedback->rendezVous?->employe?->name ?? '—' }}</td>
                    <td>{{ $feedback->note ?? '—' }}</td>
                    <td>{{ $feedback->commentaire ?? '—' }}</td>
                    <td>{{ $feedback->reponse_admin ?? '—' }}</td>
                    <td>{{ $feedback->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>