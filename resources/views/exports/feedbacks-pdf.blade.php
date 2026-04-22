<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { text-align: center; margin-bottom: 20px; }
        .feedback { margin-bottom: 15px; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
    </style>
</head>
<body>
    <h1>📄 Feedbacks</h1>

    @foreach($feedbacks as $fb)
        <div class="feedback">
            <strong>Client :</strong> {{ $fb->client->name }}<br>
            <strong>Employé :</strong> {{ $fb->rendezVous->employe->name ?? '—' }}<br>
            <strong>Note :</strong> {{ $fb->note }}/5<br>
            <strong>Commentaire :</strong><br>
            {{ $fb->commentaire }}<br>
            <strong>Date :</strong> {{ $fb->created_at->format('d/m/Y') }}
        </div>
    @endforeach
</body>
</html>
