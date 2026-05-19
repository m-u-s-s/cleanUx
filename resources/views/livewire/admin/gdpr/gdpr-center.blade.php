<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">RGPD v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre de conformité RGPD</h1>
                <p class="text-sm text-slate-500">Demandes utilisateurs, audit log, retention policies.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Exports en cours</p>
                <p class="text-2xl font-black text-indigo-600">{{ $kpis['pending_export'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Erasures programmées</p>
                <p class="text-2xl font-black text-amber-600">{{ $kpis['awaiting_erasure'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Prêtes pour exécution</p>
                <p class="text-2xl font-black text-red-600">{{ $kpis['ready_for_execution'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Comptes anonymisés</p>
                <p class="text-2xl font-black text-slate-900">{{ $kpis['anonymized_total'] }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 border-b">
            @foreach([
                'requests' => 'Demandes',
                'audit' => 'Audit log',
                'retention' => 'Retention',
            ] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        class="px-4 py-2 text-sm font-semibold border-b-2 {{ $tab === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if($currentView === 'requests')
            <div class="flex flex-wrap gap-2">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Rechercher..."
                       class="flex-1 rounded-xl border-gray-300 text-sm" />
                <select wire:model.live="filterType" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous types</option>
                    <option value="export">Export</option>
                    <option value="erasure">Erasure</option>
                    <option value="restriction">Restriction</option>
                    <option value="rectification">Rectification</option>
                </select>
                <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="awaiting_grace_period">Grace period</option>
                    <option value="fulfilled">Fulfilled</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Référence</th>
                            <th class="px-4 py-2 text-left">User</th>
                            <th class="px-4 py-2 text-left">Type</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-left">Demandé</th>
                            <th class="px-4 py-2 text-left">Exécution</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $req)
                            <tr>
                                <td class="px-4 py-2 font-mono text-xs">{{ $req->reference }}</td>
                                <td class="px-4 py-2">
                                    <p class="font-semibold">{{ $req->user?->name ?? '—' }}</p>
                                    <p class="text-xs text-slate-500">{{ $req->user?->email }}</p>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold">{{ $req->type }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $req->status === 'fulfilled',
                                        'bg-amber-100 text-amber-800' => in_array($req->status, ['processing','awaiting_grace_period']),
                                        'bg-red-100 text-red-800' => $req->status === 'rejected',
                                        'bg-slate-100 text-slate-700' => in_array($req->status, ['cancelled','expired','pending']),
                                    ])>{{ $req->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs">{{ optional($req->requested_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs">
                                    @if($req->grace_period_ends_at)
                                        {{ $req->grace_period_ends_at->format('d/m/Y') }}
                                    @elseif($req->fulfilled_at)
                                        {{ $req->fulfilled_at->format('d/m H:i') }}
                                    @else — @endif
                                </td>
                                <td class="px-4 py-2 text-right space-x-2">
                                    @if($req->type === 'erasure' && $req->status === 'awaiting_grace_period')
                                        <button wire:click="cancelRequest({{ $req->id }})"
                                                wire:confirm="Annuler cette demande d'erasure ?"
                                                class="text-xs font-semibold text-slate-600 hover:underline">Annuler</button>
                                        <button wire:click="executeNow({{ $req->id }})"
                                                wire:confirm="Exécuter MAINTENANT l'erasure ? Action irréversible."
                                                class="text-xs font-semibold text-red-600 hover:underline">Exécuter</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucune demande.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $items->links() }}</div>
            </div>
        @elseif($currentView === 'audit')
            <div class="flex flex-wrap gap-2">
                <input type="text" wire:model.live.debounce.300ms="auditSearch"
                       placeholder="Action ou target..."
                       class="flex-1 rounded-xl border-gray-300 text-sm" />
                <select wire:model.live="auditDomain" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous domaines</option>
                    <option value="security">Security</option>
                    <option value="finance">Finance</option>
                    <option value="booking">Booking</option>
                    <option value="quality">Quality</option>
                    <option value="operations">Operations</option>
                    <option value="general">General</option>
                </select>
            </div>

            <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Action</th>
                            <th class="px-4 py-2 text-left">Domain</th>
                            <th class="px-4 py-2 text-left">User</th>
                            <th class="px-4 py-2 text-left">Target</th>
                            <th class="px-4 py-2 text-left">Severity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($auditItems as $log)
                            <tr>
                                <td class="px-4 py-2 text-xs">{{ $log->created_at?->format('d/m H:i:s') }}</td>
                                <td class="px-4 py-2 font-mono text-xs">{{ $log->action }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 font-bold">{{ $log->domain ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs">{{ $log->user_id ?? 'system' }}</td>
                                <td class="px-4 py-2 text-xs">
                                    {{ $log->target_type ? class_basename($log->target_type) : '—' }}#{{ $log->target_id ?? '' }}
                                </td>
                                <td class="px-4 py-2">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-red-100 text-red-800' => $log->severity === 'error',
                                        'bg-amber-100 text-amber-800' => $log->severity === 'warning',
                                        'bg-slate-100 text-slate-700' => $log->severity === 'info',
                                    ])>{{ $log->severity ?? '—' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun log.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $auditItems instanceof \Illuminate\Contracts\Pagination\Paginator ? $auditItems->links() : '' }}</div>
            </div>
        @else
            <div class="rounded-2xl border bg-white p-6 shadow-sm space-y-4">
                <h2 class="text-lg font-bold">Retention policies</h2>
                <p class="text-sm text-slate-600">
                    Purge automatique des données dépassant leur durée de rétention RGPD.
                    Lancez via <code class="font-mono">php artisan gdpr:enforce-retention</code> ou ci-dessous.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs uppercase font-bold text-slate-500">Activity logs</p>
                        <p class="text-lg font-bold">{{ config('gdpr.retention.activity_logs_days') }} jours</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs uppercase font-bold text-slate-500">Notifications (lues)</p>
                        <p class="text-lg font-bold">{{ config('gdpr.retention.notifications_days') }} jours</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs uppercase font-bold text-slate-500">Sessions</p>
                        <p class="text-lg font-bold">{{ config('gdpr.retention.sessions_days') }} jours</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs uppercase font-bold text-slate-500">Failed jobs</p>
                        <p class="text-lg font-bold">{{ config('gdpr.retention.failed_jobs_days') }} jours</p>
                    </div>
                </div>

                <button wire:click="runRetention"
                        wire:confirm="Lancer la purge maintenant ?"
                        class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                    Exécuter la purge maintenant
                </button>
            </div>
        @endif
    </div>
</div>
