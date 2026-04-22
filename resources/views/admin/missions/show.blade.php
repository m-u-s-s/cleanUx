<x-app-layout>
    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">
                        Mission #{{ $mission->id }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Référence : {{ $mission->rendezVous?->booking_reference ?? '—' }}
                    </p>
                </div>

                <div class="text-right">
                    <div class="text-sm text-slate-500">Statut</div>
                    <div class="font-semibold text-slate-900">{{ $mission->status }}</div>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="text-sm text-slate-500">Client</div>
                    <div class="mt-1 font-medium text-slate-900">{{ $mission->rendezVous?->client?->name ?? '—' }}</div>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="text-sm text-slate-500">Employé principal</div>
                    <div class="mt-1 font-medium text-slate-900">{{ $mission->leadEmployee?->name ?? '—' }}</div>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="text-sm text-slate-500">Service</div>
                    <div class="mt-1 font-medium text-slate-900">{{ $mission->serviceCatalog?->name ?? '—' }}</div>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="text-sm text-slate-500">Zone</div>
                    <div class="mt-1 font-medium text-slate-900">{{ $mission->serviceZone?->name ?? '—' }}</div>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="text-sm text-slate-500">Début prévu</div>
                    <div class="mt-1 font-medium text-slate-900">{{ optional($mission->planned_start_at)->format('d/m/Y H:i') ?? '—' }}</div>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="text-sm text-slate-500">Début réel</div>
                    <div class="mt-1 font-medium text-slate-900">{{ optional($mission->actual_start_at)->format('d/m/Y H:i') ?? '—' }}</div>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="text-sm text-slate-500">Fin réelle</div>
                    <div class="mt-1 font-medium text-slate-900">{{ optional($mission->actual_end_at)->format('d/m/Y H:i') ?? '—' }}</div>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="text-sm text-slate-500">Score qualité</div>
                    <div class="mt-1 font-medium text-slate-900">{{ $mission->quality_score ?? '—' }}/100</div>
                </div>
            </div>

            <div class="mt-4 flex gap-3">
                <a
                    href="{{ route('admin.missions.export.pdf', $mission) }}"
                    class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-medium text-white"
                >
                    Export PDF mission
                </a>

                <a
                    href="{{ route('admin.quality.export.incidents.csv') }}"
                    class="rounded-xl bg-white px-4 py-3 text-sm font-medium text-slate-900 border border-slate-300"
                >
                    Export incidents CSV
                </a>

                <a
                    href="{{ route('admin.quality.export.missions.csv') }}"
                    class="rounded-xl bg-white px-4 py-3 text-sm font-medium text-slate-900 border border-slate-300"
                >
                    Export qualité CSV
                </a>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Équipe mission</h2>

                <div class="mt-4 space-y-3">
                    @foreach($mission->assignments as $assignment)
                        <div class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                            <div>
                                <div class="font-medium text-slate-900">{{ $assignment->user?->name ?? '—' }}</div>
                                <div class="text-sm text-slate-500">{{ $assignment->role_on_mission }}</div>
                            </div>
                            <div class="text-sm font-medium text-slate-700">{{ $assignment->assignment_status }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Tracking</h2>

                <div class="mt-4 space-y-3">
                    @foreach($mission->trackingSessions as $session)
                        <div class="rounded-xl border border-slate-200 px-4 py-3">
                            <div class="flex items-center justify-between">
                                <div class="font-medium text-slate-900">Session #{{ $session->id }}</div>
                                <div class="text-sm text-slate-500">{{ $session->tracking_mode }}</div>
                            </div>
                            <div class="mt-2 text-sm text-slate-600">
                                Début : {{ optional($session->started_at)->format('d/m/Y H:i') ?? '—' }}<br>
                                Fin : {{ optional($session->ended_at)->format('d/m/Y H:i') ?? '—' }}<br>
                                Points : {{ $session->point_count }}<br>
                                Distance : {{ number_format(($session->distance_meters ?? 0) / 1000, 2) }} km
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <livewire:admin.mission-history-panel :mission="$mission" :key="'mission-history-'.$mission->id" />
    </div>
</x-app-layout>