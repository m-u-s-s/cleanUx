<section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Action terrain</p>
            <h2 class="mt-1 text-xl font-black text-slate-900">Piloter la mission</h2>
            <p class="mt-1 text-sm text-slate-500">Changez le statut, validez les codes, lancez le tracking et signalez les incidents.</p>
        </div>
    </div>

    <div class="space-y-5">
        <livewire:employe.mission-actions
            :mission="$mission"
            :key="'field-mission-actions-'.$mission->id" />

        @if(in_array($mission->status, ['en_route', 'arrived', 'started', 'paused']))
            <livewire:employe.mission-route-tracking
                :mission="$mission"
                :key="'field-route-tracking-'.$mission->id" />
        @else
            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                Le tracking trajet devient disponible quand la mission passe en route, sur place, démarrée ou en pause.
            </div>
        @endif

        @if(in_array($mission->status, ['arrived', 'started', 'paused', 'completed']))
            <livewire:employe.mission-execution-board
                :mission="$mission"
                :key="'field-execution-board-'.$mission->id" />
        @else
            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                Le bloc exécution sera disponible dès l’arrivée sur place.
            </div>
        @endif

        <livewire:employe.mission-incident-board
            :mission="$mission"
            :key="'field-incident-board-'.$mission->id" />
    </div>
</section>
