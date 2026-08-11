@php
    /*
     * LA PAGE PUBLIQUE DE SUIVI PARTAGÉ (E3).
     *
     * Elle ne s'appuie sur AUCUN layout applicatif : le destinataire n'a pas de compte, et une
     * barre de navigation pleine de liens qui demandent une connexion est une invitation à des
     * pages d'erreur.
     */
    $suivi = $apercu['tracking'] ?? null;
    $minutes = $suivi && $suivi['eta_seconds'] ? (int) ceil($suivi['eta_seconds'] / 60) : null;
@endphp

<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Un lien de suivi circule par SMS : il ne doit pas finir indexé. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>Suivi de l'intervention</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-50 antialiased">
    <main class="mx-auto max-w-md px-4 py-10">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Intervention {{ $apercu['reference'] }}
            </p>

            <h1 class="mt-2 text-2xl font-bold text-slate-900">
                @if ($minutes !== null)
                    {{ $apercu['provider_first_name'] ?? 'Le professionnel' }} arrive dans {{ $minutes }} min
                @elseif ($suivi && $suivi['in_mission_at'])
                    L'intervention est en cours
                @elseif ($suivi && $suivi['arrived_at'])
                    {{ $apercu['provider_first_name'] ?? 'Le professionnel' }} est arrivé
                @else
                    Intervention prévue
                @endif
            </h1>

            @if ($apercu['beneficiary_name'])
            <p class="mt-1 text-sm text-slate-500">
                Pour {{ $apercu['beneficiary_name'] }}
            </p>
            @endif

            <dl class="mt-6 space-y-3 text-sm">
                @if ($apercu['scheduled_at'])
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Heure prévue</dt>
                    <dd class="font-semibold text-slate-900">
                        {{ \Illuminate\Support\Carbon::parse($apercu['scheduled_at'])->format('d/m/Y à H:i') }}
                    </dd>
                </div>
                @endif

                @if ($apercu['city'])
                <div class="flex justify-between gap-4">
                    {{-- La ville, pas l'adresse : un lien qui circule ne doit pas la diffuser. --}}
                    <dt class="text-slate-500">Lieu</dt>
                    <dd class="font-semibold text-slate-900">{{ $apercu['city'] }}</dd>
                </div>
                @endif

                @if ($suivi && $suivi['last_ping_at'])
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Dernière position</dt>
                    <dd class="font-semibold text-slate-900">
                        {{ \Illuminate\Support\Carbon::parse($suivi['last_ping_at'])->diffForHumans() }}
                    </dd>
                </div>
                @endif
            </dl>

            @unless ($suivi)
            <p class="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                Le suivi en direct s'affichera ici dès que le professionnel se mettra en route.
            </p>
            @endunless
        </div>

        <p class="mt-6 text-center text-xs text-slate-400">
            Ce lien expire {{ $apercu['expires_in_hours'] }} heures après son envoi. Il ne donne
            accès qu'à cette page.
        </p>
    </main>
</body>
</html>
