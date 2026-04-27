<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-5">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Suivi de mission</h3>
            <p class="text-sm text-slate-500">
                Statut :
                <span class="font-medium text-slate-800">{{ $mission->status }}</span>
            </p>
        </div>

        <div class="text-right text-sm text-slate-500">
            <p>Référence : <span class="font-medium text-slate-800">{{ $mission->rendezVous?->booking_reference ?? '—' }}</span></p>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-slate-200 p-4">
            <p class="text-sm text-slate-500">Employé principal</p>
            <p class="mt-1 font-medium text-slate-900">{{ $mission->leadEmployee?->name ?? 'Non assigné' }}</p>
        </div>

        <div class="rounded-xl border border-slate-200 p-4">
            <p class="text-sm text-slate-500">Heure prévue</p>
            <p class="mt-1 font-medium text-slate-900">
                {{ optional($mission->planned_start_at)->format('d/m/Y H:i') ?? '—' }}
            </p>
        </div>
    </div>

    @if($mission->status === 'en_route')
    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
        Votre employé est en route.
    </div>
    @endif

    @if(in_array($mission->status, ['en_route', 'arrived', 'started', 'paused']))
    <livewire:client.mission-live-tracking :mission="$mission" :key="'mission-live-tracking-'.$mission->id" />
    @endif

    @if($startCodeRecord && in_array($mission->status, ['arrived']))
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 space-y-2">
        <h4 class="font-semibold text-emerald-800">Code de début disponible</h4>
        <p class="text-sm text-emerald-700">
            Donnez ce code à l’employé pour démarrer la mission.
        </p>
        <div class="inline-flex rounded-xl bg-white px-4 py-2 text-xl font-bold tracking-[0.3em] text-emerald-800">
            {{ $clientStartCode ?? 'Code en attente' }}
        </div>
    </div>
    @endif

    @if($endCodeRecord && in_array($mission->status, ['started', 'paused']))
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 space-y-2">
        <h4 class="font-semibold text-amber-800">Code de fin disponible</h4>
        <p class="text-sm text-amber-700">
            Donnez ce code à l’employé pour clôturer la mission.
        </p>
        <div class="inline-flex rounded-xl bg-white px-4 py-2 text-xl font-bold tracking-[0.3em] text-amber-800">
            {{ session('mission_end_code_'.$mission->id) ?? 'Code généré côté employé' }}
        </div>
    </div>
    @endif

    @if(in_array($mission->status, ['arrived', 'started', 'paused', 'completed']))
    <livewire:client.mission-client-actions :mission="$mission" :key="'client-actions-'.$mission->id" />
    @endif

    @if($mission->status === 'completed')
    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
        Mission terminée avec succès.
    </div>
    <livewire:client.mission-qr-codes :mission="$mission" :key="'qr-codes-'.$mission->id" />
    <livewire:client.mission-aftercare-summary :mission="$mission" :key="'aftercare-'.$mission->id" />
    <livewire:client.mission-final-validation :mission="$mission" :key="'final-validation-'.$mission->id" />
    @endif
</div>
