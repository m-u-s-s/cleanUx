<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Rapport mission</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111827;
        }

        h1,
        h2,
        h3 {
            margin: 0 0 8px;
        }

        .section {
            margin-bottom: 18px;
        }

        .box {
            border: 1px solid #d1d5db;
            padding: 10px;
            border-radius: 8px;
        }

        .muted {
            color: #6b7280;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px;
            text-align: left;
        }
    </style>
</head>

<body>

    <div class="section">
        <h1>Rapport mission</h1>
        <p class="muted">Mission #{{ $mission->id }} — Référence {{ $mission->rendezVous?->booking_reference }}</p>
    </div>


    {{-- resources/views/pdf/mission-report.blade.php --}}

    <h1>Rapport de mission</h1>

    <p><strong>Client :</strong> {{ $mission->rendezVous->client->name }}</p>
    <p><strong>Employé :</strong> {{ $mission->leadEmployee->name }}</p>

    <p><strong>Début :</strong> {{ $mission->actual_start_at }}</p>
    <p><strong>Fin :</strong> {{ $mission->actual_end_at }}</p>

    <h2>Photos avant</h2>
    @foreach($mission->media->where('media_type', 'before') as $photo)
    <img src="{{ public_path('storage/'.$photo->path) }}" width="200">
    @endforeach

    <h2>Photos après</h2>
    @foreach($mission->media->where('media_type', 'after') as $photo)
    <img src="{{ public_path('storage/'.$photo->path) }}" width="200">
    @endforeach

    <h2>Checklist</h2>
    @foreach($mission->checklists as $checklist)
    <p><strong>{{ $checklist->template_name }}</strong></p>
    <ul>
        @foreach($checklist->items as $item)
        <li>
            {{ $item->label }} — {{ $item->status === 'done' ? '✔' : '✘' }}
        </li>
        @endforeach
    </ul>
    @endforeach

    
    <div class="section box">
        <h3>Informations générales</h3>
        <p><strong>Client :</strong> {{ $mission->rendezVous?->client?->name ?? '—' }}</p>
        <p><strong>Employé principal :</strong> {{ $mission->leadEmployee?->name ?? '—' }}</p>
        <p><strong>Statut :</strong> {{ $mission->status }}</p>
        <p><strong>Début réel :</strong> {{ optional($mission->actual_start_at)->format('d/m/Y H:i') ?? '—' }}</p>
        <p><strong>Fin réelle :</strong> {{ optional($mission->actual_end_at)->format('d/m/Y H:i') ?? '—' }}</p>
    </div>

    <div class="section box">
        <h3>Qualité</h3>
        <p><strong>Score qualité :</strong> {{ $mission->quality_score ?? '—' }}/100</p>
        <p><strong>Statut qualité :</strong> {{ $mission->quality_status ?? '—' }}</p>
        <p><strong>Validation client :</strong> {{ $mission->client_final_status ?? '—' }}</p>
    </div>

    <div class="section box">
        <h3>Checklist</h3>
        @php $checklist = $mission->checklists->first(); @endphp
        <p><strong>Taux de complétion :</strong> {{ $checklist?->completion_rate ?? 0 }}%</p>

        @if($checklist && $checklist->items->count())
        <table>
            <thead>
                <tr>
                    <th>Élément</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($checklist->items as $item)
                <tr>
                    <td>{{ $item->label }}</td>
                    <td>{{ $item->status }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <div class="section box">
        <h3>Médias</h3>
        <p><strong>Photos avant :</strong> {{ $mission->media->where('media_type', 'before_photo')->count() }}</p>
        <p><strong>Photos après :</strong> {{ $mission->media->where('media_type', 'after_photo')->count() }}</p>
    </div>

    <div class="section box">
        <h3>Photos</h3>

        @foreach($mission->media->where('media_type','after_photo') as $photo)
        <img src="{{ public_path('storage/'.$photo->path) }}" style="width:100px;margin:5px;">
        @endforeach
    </div>

    <div class="section box">
        <h3>Incidents</h3>
        <p><strong>Nombre d’incidents :</strong> {{ $mission->incidents->count() }}</p>
        @if($mission->incidents->count())
        <table>
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Sévérité</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($mission->incidents as $incident)
                <tr>
                    <td>{{ $incident->title }}</td>
                    <td>{{ $incident->severity }}</td>
                    <td>{{ $incident->status }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    @if($mission->client_signature_path)
    <div class="section box">
        <h3>Signature client</h3>
        <img src="{{ public_path('storage/'.$mission->client_signature_path) }}" style="width:200px;">
    </div>
    @endif

    <div class="section box">
        <h3>Historique mission</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Événement</th>
                    <th>Auteur</th>
                </tr>
            </thead>
            <tbody>
                @foreach($mission->events as $event)
                <tr>
                    <td>{{ optional($event->happened_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ $event->title }}</td>
                    <td>{{ $event->actor?->name ?? 'Système' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>

</html>