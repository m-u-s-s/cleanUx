<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">KYB v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Compliance Entreprises</h1>
                <p class="text-sm text-slate-500">Entités B2B + documents + vérifications + sanctions screening</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Entités total</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['entities_total']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Vérifiées</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['entities_verified']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En attente</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['entities_pending']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Risque critique</p>
                <p class="text-2xl font-black text-red-600">{{ number_format($kpis['critical_risk']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Sanctions match</p>
                <p class="text-2xl font-black text-red-600">{{ number_format($kpis['sanctions_matches']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['entities' => 'Entités', 'documents' => 'Documents', 'verifications' => 'Vérifications', 'sanctions' => 'Sanctions'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        @if($tab === 'entities')
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="pending">Pending</option>
                    <option value="verified">Verified</option>
                    <option value="rejected">Rejected</option>
                    <option value="suspended">Suspended</option>
                    <option value="needs_review">Needs review</option>
                </select>
                <select wire:model.live="filterRiskLevel" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous risques</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                </select>
            </div>
        @endif

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'entities')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Légal</th>
                            <th class="px-4 py-2 text-left">Pays</th>
                            <th class="px-4 py-2 text-left">Identifiant</th>
                            <th class="px-4 py-2 text-left">TVA</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-left">Risque</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $e)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ \Illuminate\Support\Str::limit($e->code, 14) }}</td>
                                <td class="px-4 py-2 text-xs">{{ $e->legal_name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $e->country_code }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $e->identifier_type }}:{{ $e->identifier_value }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $e->vat_id ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $e->status === 'verified',
                                        'bg-amber-100 text-amber-800' => in_array($e->status, ['pending', 'needs_review']),
                                        'bg-red-100 text-red-800' => $e->status === 'rejected',
                                        'bg-slate-100 text-slate-800' => $e->status === 'suspended',
                                    ])>{{ $e->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs">
                                    @if($e->risk_level)
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                            'bg-emerald-100 text-emerald-800' => $e->risk_level === 'low',
                                            'bg-amber-100 text-amber-800' => $e->risk_level === 'medium',
                                            'bg-orange-100 text-orange-800' => $e->risk_level === 'high',
                                            'bg-red-100 text-red-800' => $e->risk_level === 'critical',
                                        ])>{{ $e->risk_level }} ({{ number_format($e->risk_score, 1) }})</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right text-xs space-x-2">
                                    <button wire:click="runVerifications({{ $e->id }})" class="text-indigo-600 hover:underline">Vérifier</button>
                                    <button wire:click="runSanctions({{ $e->id }})" class="text-amber-600 hover:underline">Sanctions</button>
                                    @if($e->status !== 'verified')
                                        <button wire:click="approveEntity({{ $e->id }})" class="text-emerald-600 hover:underline">Approuver</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Aucune entité.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'documents')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Entité</th>
                            <th class="px-4 py-2 text-left">Type</th>
                            <th class="px-4 py-2 text-left">Taille</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $d)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($d->uploaded_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $d->entity?->legal_name }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $d->document_type }}</td>
                                <td class="px-4 py-2 text-xs">{{ number_format($d->size_bytes / 1024, 1) }} KB</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $d->status === 'approved',
                                        'bg-amber-100 text-amber-800' => $d->status === 'pending',
                                        'bg-red-100 text-red-800' => $d->status === 'rejected',
                                        'bg-slate-100 text-slate-800' => $d->status === 'expired',
                                    ])>{{ $d->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-right text-xs">
                                    @if($d->status === 'pending')
                                        <button wire:click="approveDocument({{ $d->id }})" class="text-emerald-600 hover:underline">Approuver</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun document.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'verifications')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Entité</th>
                            <th class="px-4 py-2 text-left">Provider</th>
                            <th class="px-4 py-2 text-left">Type</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-left">Match</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $v)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($v->checked_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $v->entity?->legal_name }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $v->provider }}</td>
                                <td class="px-4 py-2 text-xs">{{ $v->check_type }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $v->status === 'success',
                                        'bg-amber-100 text-amber-800' => $v->status === 'pending',
                                        'bg-red-100 text-red-800' => in_array($v->status, ['failed', 'error']),
                                    ])>{{ $v->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $v->matched_value ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucune vérification.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Entité</th>
                            <th class="px-4 py-2 text-left">Liste</th>
                            <th class="px-4 py-2 text-left">Provider</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-left">Matches</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $s)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($s->checked_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $s->entity?->legal_name }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $s->list_name }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $s->provider }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $s->status === 'clear',
                                        'bg-red-100 text-red-800' => $s->status === 'match',
                                        'bg-amber-100 text-amber-800' => in_array($s->status, ['pending', 'review_required']),
                                        'bg-slate-100 text-slate-800' => $s->status === 'error',
                                    ])>{{ $s->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs">{{ $s->match_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun screening.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
