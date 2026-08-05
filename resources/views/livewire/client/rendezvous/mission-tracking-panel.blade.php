<div class="bg-white border rounded-xl p-4 space-y-4">
    <p class="text-sm font-semibold text-slate-800">🧭 Suivi de mission</p>

    <div class="flex flex-wrap gap-2 text-xs">
        <span class="px-3 py-1 rounded-full {{ in_array($rdv->status, ['en_attente','confirme','en_route','sur_place','termine','annule']) ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500' }}">
            Demande reçue
        </span>
        <span class="px-3 py-1 rounded-full {{ in_array($rdv->status, ['confirme','en_route','sur_place','termine']) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
            Confirmée
        </span>
        <span class="px-3 py-1 rounded-full {{ in_array($rdv->status, ['en_route','sur_place','termine']) ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}">
            En route
        </span>
        <span class="px-3 py-1 rounded-full {{ in_array($rdv->status, ['sur_place','termine']) ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500' }}">
            Sur place
        </span>
        <span class="px-3 py-1 rounded-full {{ $rdv->status === 'termine' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
            Terminée
        </span>
    </div>

    {{--
        LA CARTE DE SUIVI PLEIN ÉCRAN N'AVAIT AUCUN LIEN (ajouté le 2026-08-05).

        Cette tuile affiche l'avancement en ligne ; `client.booking.tracking.map` est la carte en
        direct, sur toute la page — utile en déplacement, quand on suit l'arrivée du prestataire.
        Elle existait sans qu'aucun écran n'y mène.

        `$rdv` est un Booking (MesRendezVousClient interroge `Booking::`), donc son `id` EST le
        `bookingId` attendu — vérifié plutôt que supposé, le nom de la variable dit le contraire.
    --}}
    @if(Route::has('client.booking.tracking.map') && in_array($rdv->status, ['confirme', 'en_route', 'sur_place'], true))
    <div class="mb-3">
        <a href="{{ route('client.booking.tracking.map', $rdv->id) }}"
           class="inline-flex items-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">
            🗺️ Suivre sur la carte
        </a>
    </div>
    @endif

    @if($rdv->mission)
    <livewire:client.mission-tracking :mission="$rdv->mission" :key="'mission-tracking-'.$rdv->mission->id" />
    @else
    @php
    $operationalMission = method_exists($rdv, 'operationalMission')
    ? $rdv->operationalMission()
    : null;
    @endphp

    @if($operationalMission)
    @php
    $operationalMission = method_exists($rdv, 'operationalMission')
    ? $rdv->operationalMission()
    : null;

    $missionTrackingComponent = collect([
    \App\Livewire\Client\MissionTrackingPanel::class,
    \App\Livewire\Client\MissionTracking::class,
    \App\Livewire\Client\ClientMissionTrackingPanel::class,
    \App\Livewire\Client\RendezVous\MissionTrackingPanel::class,
    \App\Livewire\Client\RendezVous\MissionTracking::class,
    ])->first(fn ($class) => class_exists($class));
    @endphp

    @if($operationalMission && $missionTrackingComponent)
    @livewire($missionTrackingComponent, ['mission' => $operationalMission], key('mission-tracking-'.$operationalMission->id))
    @elseif($operationalMission)
    <div class="rounded-xl border bg-white p-4 text-sm text-slate-700">
        <p class="font-semibold text-slate-800">Code de début disponible</p>
        <p class="mt-1 text-slate-500">Le suivi mission est disponible pour cette intervention.</p>
    </div>
    @else
    <p class="text-sm text-slate-500">
        Le suivi mission détaillé apparaîtra dès qu’une mission opérationnelle sera synchronisée.
    </p>
    @endif
    @else
    <p class="text-sm text-slate-500">
        Le suivi mission détaillé apparaîtra dès qu’une mission opérationnelle sera synchronisée.
    </p>
    @endif
    @endif
</div>