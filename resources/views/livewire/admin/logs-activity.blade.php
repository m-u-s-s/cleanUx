<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Recherche</label>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Action, utilisateur, cible..."
                class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500"
            >
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Action</label>
            <select
                wire:model.live="actionFilter"
                class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500"
            >
                <option value="">Toutes les actions</option>
                @foreach($availableActions as $action)
                    <option value="{{ $action }}">{{ $action }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Résultats par page</label>
            <select
                wire:model.live="perPage"
                class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500"
            >
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 bg-white">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Utilisateur</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cible</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Meta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($logs as $log)
                    <tr>
                        <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">
                            {{ optional($log->created_at)->format('d/m/Y H:i') }}
                        </td>

                        <td class="px-4 py-3 text-sm text-slate-700">
                            @if($log->user)
                                <div class="font-semibold">{{ $log->user->name }}</div>
                                <div class="text-xs text-slate-500">{{ $log->user->email }}</div>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                    Système
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex items-center rounded-full bg-sky-50 border border-sky-200 px-2.5 py-1 text-xs font-semibold text-sky-700">
                                {{ $log->action }}
                            </span>
                        </td>

                        <td class="px-4 py-3 text-sm text-slate-600">
                            <div>{{ $log->target_type ? class_basename($log->target_type) : '—' }}</div>
                            <div class="text-xs text-slate-500">ID: {{ $log->target_id ?? '—' }}</div>
                        </td>

                        <td class="px-4 py-3 text-xs text-slate-600 align-top">
                            @if(!empty($log->meta))
                                <pre class="whitespace-pre-wrap break-words text-xs bg-slate-50 border border-slate-200 rounded-xl p-3">{{ json_encode($log->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            @else
                                <span class="text-slate-400">Aucune donnée</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">
                            Aucun log d’activité trouvé.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $logs->links() }}
    </div>
</div>