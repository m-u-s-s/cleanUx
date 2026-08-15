@php
    $statsJour = $statsJour ?? [
        'total' => 0,
        'a_faire' => 0,
        'en_cours' => 0,
        'terminees' => 0,
        'refusees' => 0,
        'urgentes' => 0,
        'heures_prevues' => 0,
        'progression' => 0,
    ];

    $missionsDuJour = $missionsDuJour ?? collect();
    $historiqueRecent = $historiqueRecent ?? collect();
    $assignedZones = $assignedZones ?? collect();
    $missionsHorsZone = $missionsHorsZone ?? collect();
    $urgencesDuJour = $urgencesDuJour ?? collect();
    $prochaineMission = $prochaineMission ?? null;
    $paymentStatus = $paymentStatus ?? ['ready' => false, 'label' => 'Paiement à configurer'];
@endphp

<div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-7xl space-y-8 px-4 pb-10 pt-6 sm:px-6 lg:px-8">
        <x-active-sessions />

        {{--
            Ce qui empêche — ou empêchera — de recevoir des courses. Placé AVANT le reste : un
            prestataire qui ne reçoit plus rien ouvre son tableau de bord en premier, et c'est là
            qu'il doit trouver la réponse, pas dans un écran de réglages qu'il n'a aucune raison
            d'ouvrir.
        --}}
        {{--
            LE DOSSIER D'ABORD, LA CONDUITE ENSUITE — l'ordre n'est pas décoratif.

            Tant que le compte n'est pas vérifié, AUCUNE mission n'arrive, quel que soit le métier :
            c'est la première chose à dire. Les exigences de conduite ne concernent qu'une partie
            des métiers et n'ont de sens qu'une fois cette porte franchie.
        --}}
        <livewire:provider.provider-dossier-banner />

        <livewire:provider.provider-driving-banner />


        @include('livewire.employe.dashboard.hero')

        @include('livewire.employe.dashboard.kpis')

        @include('livewire.employe.dashboard.next-mission')

        @include('livewire.employe.dashboard.main-content')

        @include('livewire.employe.dashboard.capacity-and-actions')
    </div>
</div>

@include('livewire.employe.dashboard.offline-sync-scripts')
