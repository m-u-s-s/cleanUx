<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Contracts v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Contrats & Signatures</h1>
                <p class="text-sm text-slate-500">Templates versionnés + documents + signatures eIDAS-lite</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Templates actifs</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['templates_active']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Documents en attente</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['documents_pending']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Signatures valides</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['signatures_valid']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Invalidées</p>
                <p class="text-2xl font-black text-red-600">{{ number_format($kpis['signatures_invalidated']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['templates' => 'Templates', 'documents' => 'Documents', 'signatures' => 'Signatures'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        @if($tab === 'templates')
            <div class="flex flex-wrap gap-2">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Code / nom..." class="flex-1 rounded-xl border-gray-300 text-sm" />
                <select wire:model.live="filterType" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous types</option>
                    <option value="tos">TOS</option>
                    <option value="sla">SLA</option>
                    <option value="client_agreement">Client agreement</option>
                    <option value="provider_agreement">Provider agreement</option>
                    <option value="nda">NDA</option>
                    <option value="other">Autre</option>
                </select>
            </div>
        @elseif($tab === 'documents')
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="draft">Draft</option>
                    <option value="pending_signature">Pending</option>
                    <option value="signed">Signed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="expired">Expired</option>
                </select>
            </div>
        @endif

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'templates')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-left">Type</th>
                            <th class="px-4 py-2 text-left">Role</th>
                            <th class="px-4 py-2 text-left">Version</th>
                            <th class="px-4 py-2 text-left">Active</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $t)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $t->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $t->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $t->type }}</td>
                                <td class="px-4 py-2 text-xs">{{ $t->role }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $t->version }}</td>
                                <td class="px-4 py-2 text-xs">{{ $t->is_active ? '✓' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun template.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'documents')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Template</th>
                            <th class="px-4 py-2 text-left">User</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-left">PDF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $d)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $d->generated_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $d->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $d->template?->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $d->user?->email }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $d->status === 'signed',
                                        'bg-amber-100 text-amber-800' => $d->status === 'pending_signature',
                                        'bg-slate-100 text-slate-800' => in_array($d->status, ['draft', 'cancelled', 'expired']),
                                    ])>{{ $d->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs">{{ $d->pdf_path ? '✓' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun document.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Signé</th>
                            <th class="px-4 py-2 text-left">Document</th>
                            <th class="px-4 py-2 text-left">Signer</th>
                            <th class="px-4 py-2 text-left">Hash</th>
                            <th class="px-4 py-2 text-left">Valide</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $s)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $s->signed_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $s->document?->template?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $s->signer_name }}</td>
                                <td class="px-4 py-2 text-xs font-mono" title="{{ $s->signature_hash }}">{{ \Illuminate\Support\Str::limit($s->signature_hash, 16) }}</td>
                                <td class="px-4 py-2 text-xs">
                                    @if($s->is_invalidated)
                                        <span class="text-red-600">✗ invalidé</span>
                                    @else
                                        <span class="text-emerald-600">✓</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right text-xs">
                                    @if(! $s->is_invalidated)
                                        <button wire:click="invalidate({{ $s->id }})" class="text-red-600 hover:underline">Invalider</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucune signature.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
