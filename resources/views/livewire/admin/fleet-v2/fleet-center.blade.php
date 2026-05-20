<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Fleet v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Véhicules & Matériel</h1>
                <p class="text-sm text-slate-500">Vehicules + équipements + assignations + maintenance + certifications</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="scanExpiring" class="rounded-xl border bg-amber-50 text-amber-800 px-4 py-2 text-sm font-semibold hover:bg-amber-100">
                    Scan expiration ({{ $expiringSoonDays }}j)
                </button>
                <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-7 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Véhicules</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['vehicles_total']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Disponibles</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['vehicles_available']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En usage</p>
                <p class="text-2xl font-black text-blue-600">{{ number_format($kpis['vehicles_in_use']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Équipements</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['equipment_total']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Assign. actives</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['assignments_active']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Cert. soon</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['certs_expiring_soon']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Cert. expirées</p>
                <p class="text-2xl font-black text-red-600">{{ number_format($kpis['certs_expired']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['vehicles' => 'Véhicules', 'equipment' => 'Équipements', 'assignments' => 'Assignations', 'maintenance' => 'Maintenance', 'certifications' => 'Certifications'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'vehicles')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Plate</th>
                            <th class="px-4 py-2 text-left">Brand/Model</th>
                            <th class="px-4 py-2 text-left">Type</th>
                            <th class="px-4 py-2 text-left">Carburant</th>
                            <th class="px-4 py-2 text-left">Km</th>
                            <th class="px-4 py-2 text-left">Assurance</th>
                            <th class="px-4 py-2 text-left">CT</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $v)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $v->plate }}</td>
                                <td class="px-4 py-2 text-xs">{{ $v->brand }} {{ $v->model }} ({{ $v->year }})</td>
                                <td class="px-4 py-2 text-xs">{{ $v->vehicle_type }}</td>
                                <td class="px-4 py-2 text-xs">{{ $v->fuel_type ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $v->odometer_km ? number_format($v->odometer_km) : '—' }}</td>
                                <td class="px-4 py-2 text-xs">
                                    @if($v->insurance_expires_at)
                                        <span @class([
                                            'text-emerald-600' => $v->insurance_expires_at->isAfter(now()->addDays(60)),
                                            'text-amber-600' => $v->insurance_expires_at->isBefore(now()->addDays(60)) && $v->insurance_expires_at->isAfter(now()),
                                            'text-red-600' => $v->insurance_expires_at->isPast(),
                                        ])>{{ $v->insurance_expires_at->format('d/m/Y') }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs">
                                    @if($v->control_technique_expires_at)
                                        <span @class([
                                            'text-emerald-600' => $v->control_technique_expires_at->isAfter(now()->addDays(60)),
                                            'text-amber-600' => $v->control_technique_expires_at->isBefore(now()->addDays(60)) && $v->control_technique_expires_at->isAfter(now()),
                                            'text-red-600' => $v->control_technique_expires_at->isPast(),
                                        ])>{{ $v->control_technique_expires_at->format('d/m/Y') }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $v->status === 'available',
                                        'bg-blue-100 text-blue-800' => $v->status === 'in_use',
                                        'bg-amber-100 text-amber-800' => $v->status === 'maintenance',
                                        'bg-slate-100 text-slate-800' => $v->status === 'retired',
                                    ])>{{ $v->status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Aucun véhicule.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'equipment')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-left">Type</th>
                            <th class="px-4 py-2 text-left">Catégorie</th>
                            <th class="px-4 py-2 text-left">Valeur</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $e)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ \Illuminate\Support\Str::limit($e->code, 14) }}</td>
                                <td class="px-4 py-2 text-xs">{{ $e->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $e->equipment_type }}</td>
                                <td class="px-4 py-2 text-xs">{{ $e->category ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $e->value_cents ? number_format($e->value_cents / 100, 0, ',', ' ') . ' €' : '—' }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $e->status === 'available',
                                        'bg-blue-100 text-blue-800' => $e->status === 'in_use',
                                        'bg-amber-100 text-amber-800' => $e->status === 'maintenance',
                                        'bg-red-100 text-red-800' => $e->status === 'lost',
                                        'bg-slate-100 text-slate-800' => $e->status === 'retired',
                                    ])>{{ $e->status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun équipement.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'assignments')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Sujet</th>
                            <th class="px-4 py-2 text-left">Provider</th>
                            <th class="px-4 py-2 text-left">Retour prévu</th>
                            <th class="px-4 py-2 text-left">Retourné</th>
                            <th class="px-4 py-2 text-left">Condition</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $a)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($a->assigned_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $a->vehicle?->plate ?? $a->equipment?->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $a->provider?->email }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($a->expected_return_at)->format('d/m') ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($a->returned_at)->format('d/m H:i') ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $a->returned_condition ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-blue-100 text-blue-800' => $a->status === 'active',
                                        'bg-emerald-100 text-emerald-800' => $a->status === 'completed',
                                        'bg-slate-100 text-slate-800' => $a->status === 'cancelled',
                                    ])>{{ $a->status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucune assignation.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'maintenance')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Sujet</th>
                            <th class="px-4 py-2 text-left">Type</th>
                            <th class="px-4 py-2 text-left">Coût</th>
                            <th class="px-4 py-2 text-left">Prochain dû</th>
                            <th class="px-4 py-2 text-left">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $m)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($m->performed_at)->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $m->vehicle?->plate ?? $m->equipment?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $m->maintenance_type }}</td>
                                <td class="px-4 py-2 text-xs">{{ $m->cost_cents ? number_format($m->cost_cents / 100, 2, ',', ' ') . ' €' : '—' }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($m->next_due_at)->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ \Illuminate\Support\Str::limit($m->notes ?? '—', 50) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucune maintenance.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Sujet</th>
                            <th class="px-4 py-2 text-left">Type</th>
                            <th class="px-4 py-2 text-left">Référence</th>
                            <th class="px-4 py-2 text-left">Émis</th>
                            <th class="px-4 py-2 text-left">Expire</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $c)
                            <tr>
                                <td class="px-4 py-2 text-xs">{{ $c->subject_type }} #{{ $c->subject_id }}</td>
                                <td class="px-4 py-2 text-xs">{{ $c->certification_type }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $c->reference ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($c->issued_at)->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">
                                    @if($c->expires_at)
                                        <span @class([
                                            'text-emerald-600' => $c->status === 'active',
                                            'text-amber-600' => $c->status === 'expiring_soon',
                                            'text-red-600' => $c->status === 'expired',
                                            'text-slate-500' => $c->status === 'revoked',
                                        ])>{{ $c->expires_at->format('d/m/Y') }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $c->status === 'active',
                                        'bg-amber-100 text-amber-800' => $c->status === 'expiring_soon',
                                        'bg-red-100 text-red-800' => $c->status === 'expired',
                                        'bg-slate-100 text-slate-800' => $c->status === 'revoked',
                                    ])>{{ $c->status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucune certification.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
