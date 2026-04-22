<div class="p-4 md:p-6 space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Mon Google Agenda</h2>
            <p class="text-sm text-slate-500">Connecte ton agenda pour recevoir automatiquement tes missions CleanUx.</p>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow border p-5">
            <p class="text-sm text-slate-500">Connexion</p>
            <p class="text-lg font-semibold text-slate-900 mt-2">{{ $connection ? 'Connectée' : 'Non connectée' }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow border p-5">
            <p class="text-sm text-slate-500">Google email</p>
            <p class="text-lg font-semibold text-slate-900 mt-2">{{ $connection?->google_email ?? '—' }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow border p-5">
            <p class="text-sm text-slate-500">Dernière sync</p>
            <p class="text-lg font-semibold text-slate-900 mt-2">{{ $connection?->last_synced_at?->diffForHumans() ?? 'Jamais' }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow border p-5">
            <p class="text-sm text-slate-500">Missions à venir</p>
            <p class="text-3xl font-bold text-slate-900 mt-2">{{ $upcomingCount }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow border p-5 space-y-4">
        <p class="text-sm text-slate-600">Une fois connecté, tes missions futures seront synchronisées régulièrement vers Google Calendar.</p>

        @if($connection?->last_sync_error)
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                Dernière erreur de synchronisation : {{ $connection->last_sync_error }}
            </div>
        @endif

        @if($nextMission)
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-sm font-semibold text-slate-900">Prochaine mission</p>
                <p class="text-sm text-slate-700 mt-1">
                    {{ $nextMission->service_display_name ?: 'Mission non précisée' }}
                    · {{ $nextMission->date?->format('d/m/Y') }} à {{ substr((string) $nextMission->heure, 0, 5) }}
                </p>
                <p class="text-xs text-slate-500 mt-1">Zone : {{ $nextMission->serviceZone?->name ?? '—' }}</p>
            </div>
        @endif

        <div class="flex gap-3">
            @if($connection)
                <form method="POST" action="{{ route('google.calendar.disconnect') }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 text-white font-semibold hover:bg-red-700">Déconnecter Google</button>
                </form>
            @else
                <a href="{{ route('google.calendar.connect') }}" class="px-4 py-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700">Connecter Google Agenda</a>
            @endif
        </div>
    </div>
</div>
