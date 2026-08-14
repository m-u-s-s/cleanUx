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
    {{-- Chargé UNIQUEMENT quand il y a une position à montrer : cette page s'ouvre souvent en
         itinérance, et payer deux requêtes de bibliothèque pour un écran sans carte serait
         gratuit — dans le mauvais sens du terme. --}}
    @if (($apercu['tracking']['lat'] ?? null) && ($apercu['tracking']['lng'] ?? null))
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    @endif
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

            {{--
                LA CARTE — ce pour quoi le lien a été envoyé.

                Cette page annonçait « il arrive dans 12 min » et ne montrait rien. Or le destinataire
                ouvre ce lien pour VOIR où en est la voiture, pas pour lire un chiffre qu'un SMS
                aurait dit aussi bien. Leaflet sur tuiles OpenStreetMap : aucune clé, aucun compte.

                ELLE RESTE AUSSI PAUVRE QUE LE RESTE : des points, jamais une adresse ni un nom. Le
                destinataire voit vers où l'on se dirige sans lire une rue.
            --}}
            @if ($suivi && $suivi['lat'] && $suivi['lng'])
                <div id="suivi-carte" class="mt-6 h-64 w-full overflow-hidden rounded-2xl border border-slate-200"></div>
            @endif

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

    @if ($suivi && $suivi['lat'] && $suivi['lng'])
    <script>
        /*
         * La carte est POSÉE UNE FOIS, sans rafraîchissement.
         *
         * La page est servie au chargement et n'a pas de canal temps réel : simuler un suivi vivant
         * par un sondage ferait tourner une requête toutes les quinze secondes sur un lien qui
         * circule par SMS — et qu'on laisse ouvert dans un onglet oublié. Recharger la page suffit,
         * et c'est ce que fait tout le monde.
         */
        window.addEventListener('load', function () {
            const cible = document.getElementById('suivi-carte');

            if (!cible || typeof L === 'undefined') {
                return;
            }

            const prestataire = @js([$suivi['lat'], $suivi['lng']]);
            const destination = @js(
                ($suivi['destination']['lat'] ?? null) && ($suivi['destination']['lng'] ?? null)
                    ? [$suivi['destination']['lat'], $suivi['destination']['lng']]
                    : null
            );
            const trace = @js($suivi['route']['points'] ?? null);

            const carte = L.map(cible, { attributionControl: true }).setView(prestataire, 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(carte);

            L.marker(prestataire).addTo(carte);

            if (destination) {
                L.marker(destination).addTo(carte);
            }

            const points = (trace && trace.length > 1)
                ? trace.map(p => [p.lat, p.lng])
                : (destination ? [prestataire, destination] : null);

            if (points) {
                const ligne = L.polyline(points, { color: '#0f172a', weight: 4, opacity: 0.6 }).addTo(carte);
                carte.fitBounds(ligne.getBounds(), { padding: [30, 30] });
            }
        });
    </script>
    @endif
</body>
</html>
