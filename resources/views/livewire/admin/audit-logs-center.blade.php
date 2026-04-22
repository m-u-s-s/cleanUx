<div class="space-y-6">
    <x-page-shell
        title="Centre d’audit et logs"
        subtitle="Visualise les actions sensibles, le contexte sécurité et les traces d’administration dans une seule interface."
        eyebrow="Sécurité & audit"
    >
        <x-slot:actions>
            <span class="cu-inline-stat">{{ $logs->total() }} log(s)</span>
            @if($criticalOnly)
                <span class="cu-inline-stat">Mode critique</span>
            @endif
        </x-slot:actions>
    </x-page-shell>

    <x-filter-panel title="Filtres d’audit" subtitle="Isole rapidement un acteur, une action ou une sévérité.">
        <div class="cu-filter-grid">
            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Recherche</label>
                <input wire:model.live.debounce.400ms="search" type="text" placeholder="Action, route, requête, cible…">
            </div>
            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Sévérité</label>
                <select wire:model.live="severityFilter">
                    <option value="">Toutes</option>
                    @foreach($severities as $severity)
                        <option value="{{ $severity }}">{{ ucfirst($severity) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Action</label>
                <select wire:model.live="actionFilter">
                    <option value="">Toutes</option>
                    @foreach($availableActions as $action)
                        <option value="{{ $action }}">{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Acteur</label>
                <select wire:model.live="actorFilter">
                    <option value="">Tous</option>
                    <option value="human">Utilisateur</option>
                    <option value="system">Système</option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Zone</label>
                <select wire:model.live="zoneFilter" @disabled(auth()->user()?->isZoneScopedAdmin())>
                    <option value="">Toutes</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Résultats</label>
                <select wire:model.live="perPage">
                    <option value="15">15</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>

        <label class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" wire:model.live="criticalOnly" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
            Uniquement les actions sensibles
        </label>
    </x-filter-panel>

    <div wire:loading.delay>
        <x-loading-panel message="Chargement des logs d’audit…" />
    </div>

    <div wire:loading.remove>
        <x-table-shell title="Journal d’activité" subtitle="Historique détaillé des événements, acteurs et métadonnées.">
            <table class="min-w-full cu-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Acteur</th>
                        <th>Action</th>
                        <th>Contexte</th>
                        <th>Méta</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap text-sm text-slate-600">
                                <div>{{ optional($log->created_at)->format('d/m/Y H:i') }}</div>
                                @if($log->request_id)
                                    <div class="text-xs text-slate-400">{{ $log->request_id }}</div>
                                @endif
                            </td>
                            <td class="text-sm text-slate-700">
                                @if($log->user)
                                    <div class="font-semibold">{{ $log->user->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $log->user->email }}</div>
                                @else
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Système</span>
                                @endif
                            </td>
                            <td class="text-sm">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $log->severity === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : ($log->severity === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-sky-200 bg-sky-50 text-sky-700') }}">{{ $log->action }}</span>
                                    @if($log->is_critical)
                                        <span class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">Critique</span>
                                    @endif
                                </div>
                                <div class="mt-2 text-xs text-slate-500">Domaine: {{ $log->domain ?? '—' }} · Sévérité: {{ $log->severity ?? 'info' }}</div>
                            </td>
                            <td class="text-sm text-slate-600">
                                <div>{{ $log->target_type ? class_basename($log->target_type) : '—' }}</div>
                                <div class="text-xs text-slate-500">ID: {{ $log->target_id ?? '—' }}</div>
                                <div class="mt-2 text-xs text-slate-500">Route: {{ $log->route_name ?? '—' }}</div>
                                <div class="text-xs text-slate-500">Zone: {{ $log->serviceZone?->name ?? '—' }}</div>
                            </td>
                            <td class="align-top text-xs text-slate-600">
                                @if(!empty($log->meta))
                                    <pre class="whitespace-pre-wrap break-words rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs">{{ json_encode($log->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                @else
                                    <span class="text-slate-400">Aucune donnée</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-empty-state title="Aucun log trouvé" message="Aucun événement ne correspond à ces filtres." icon="🛡️" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $logs->links() }}</div>
        </x-table-shell>
    </div>
</div>
