@php
    $rdv = $mission->booking;
    $client = $rdv?->client;

    /*
     * Sur une course, une seule adresse ne suffit pas : le chauffeur a besoin de savoir OÙ IL VA
     * avant que le client ne monte, pas seulement où il vient le chercher.
     *
     * Commentaire de bloc et non `//` : Blade peut réduire un bloc @php à une seule ligne, et un
     * commentaire de fin de ligne y avalerait l'affectation qui suit.
     */
    $estUneCourse = (bool) $rdv?->estUneCourse();
@endphp

<section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Client & accès</p>
            <h2 class="mt-1 text-xl font-black text-slate-900">Informations d’intervention</h2>
        </div>

        @if($rdv?->priorite)
            <x-priority-badge :priority="$rdv->priorite" />
        @endif
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Client</p>
            <p class="mt-1 font-black text-slate-900">{{ $client?->name ?? 'Client non précisé' }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $client?->email ?? '—' }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Téléphone</p>
            <p class="mt-1 font-black text-slate-900">{{ $rdv?->contact_phone ?? '—' }}</p>
            @if($rdv?->contact_phone)
                <a href="tel:{{ $rdv->contact_phone }}" class="mt-2 inline-flex text-sm font-bold text-blue-700 hover:text-blue-900">Appeler maintenant</a>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                {{ $estUneCourse ? 'Prise en charge' : 'Adresse' }}
            </p>
            <p class="mt-1 font-black text-slate-900">
                {{ $rdv?->adresse ?? 'Adresse non précisée' }}{{ $rdv?->ville ? ', '.$rdv->ville : '' }}
            </p>
            @if($rdv?->code_postal || $rdv?->serviceZone?->name)
                <p class="mt-1 text-sm text-slate-500">
                    {{ $rdv?->code_postal }} {{ $rdv?->serviceZone?->name ? '· '.$rdv->serviceZone->name : '' }}
                </p>
            @endif
        </div>

        {{--
            LA DESTINATION. Elle manquait, et c'est celle qui décide du trajet : un chauffeur
            lisait le point où il devait aller CHERCHER quelqu'un, jamais celui où il devait
            l'emmener. La distance et la durée sont là aussi — c'est sur elles qu'on juge si l'on
            a le temps avant la course suivante.
        --}}
        @if($estUneCourse)
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 sm:col-span-2">
                <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Destination</p>
                <p class="mt-1 font-black text-emerald-950">
                    {{ $rdv?->dropoff_address ?: 'Point d’arrivée sur la carte' }}
                </p>
                @if($rdv?->route_distance_m)
                    <p class="mt-1 text-sm text-emerald-800">
                        {{ str_replace('.', ',', (string) round($rdv->route_distance_m / 1000, 1)) }} km
                        @if($rdv?->route_duration_s)
                            · environ {{ (int) ceil($rdv->route_duration_s / 60) }} min
                        @endif
                    </p>
                @endif
                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $rdv->dropoff_lat }},{{ $rdv->dropoff_lng }}"
                    target="_blank" rel="noopener"
                    class="mt-2 inline-flex text-sm font-bold text-emerald-800 hover:text-emerald-950">
                    Itinéraire vers la destination
                </a>
            </div>
        @endif

        {{--
            CE PANNEAU LISAIT `bookings.notes`, QUE RIEN N'ÉCRIT — IL NE S'EST JAMAIS AFFICHÉ.

            Le commentaire du client vit dans `customer_comment` / `customer_comment` : c'est là
            que le parcours de commande le range, là que l'API le reçoit, et là que le tableau de
            bord prestataire et la page d'offre le lisent déjà. La confusion vient du formulaire
            société, dont le champ s'appelle « notes » et qui écrit `customer_comment`.

            Conséquence : le prestataire sur place ne voyait aucune consigne d'accès — ni « portail
            au fond de la cour », ni « sonner deux fois » — alors que le client les avait données.
        --}}
        @if($rdv?->customer_comment)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:col-span-2">
                <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Notes client</p>
                <p class="mt-1 text-sm leading-6 text-amber-900">{{ $rdv->customer_comment }}</p>
            </div>
        @endif

        @if($mission->notes)
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 sm:col-span-2">
                <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Notes mission</p>
                <p class="mt-1 text-sm leading-6 text-blue-900">{{ $mission->notes }}</p>
            </div>
        @endif
    </div>
</section>
