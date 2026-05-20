<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Tenancy v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Multi-tenancy & White-label</h1>
                <p class="text-sm text-slate-500">Tenants + domaines + utilisateurs scoped + theming</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Tenants total</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['tenants_total']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Actifs</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['tenants_active']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Trial</p>
                <p class="text-2xl font-black text-blue-600">{{ number_format($kpis['tenants_trial']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Suspendus</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['tenants_suspended']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Domaines vérifiés</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['domains_verified']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['tenants' => 'Tenants', 'domains' => 'Domaines', 'users' => 'Utilisateurs'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        @if($tab === 'tenants')
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="active">Active</option>
                    <option value="trial">Trial</option>
                    <option value="suspended">Suspended</option>
                    <option value="archived">Archived</option>
                </select>
                <select wire:model.live="filterPlan" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous plans</option>
                    <option value="basic">Basic</option>
                    <option value="growth">Growth</option>
                    <option value="enterprise">Enterprise</option>
                </select>
            </div>
        @endif

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'tenants')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-left">Plan</th>
                            <th class="px-4 py-2 text-left">Domaine</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-left">Trial fin</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $t)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $t->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $t->name }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-mono">{{ $t->plan_code }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $t->primary_domain ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $t->status === 'active',
                                        'bg-blue-100 text-blue-800' => $t->status === 'trial',
                                        'bg-amber-100 text-amber-800' => $t->status === 'suspended',
                                        'bg-slate-100 text-slate-800' => $t->status === 'archived',
                                    ])>{{ $t->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($t->trial_ends_at)->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-2 text-right text-xs space-x-2">
                                    @if($t->status === 'suspended')
                                        <button wire:click="activateTenant({{ $t->id }})" class="text-emerald-600 hover:underline">Activer</button>
                                    @elseif($t->status !== 'archived')
                                        <button wire:click="suspendTenant({{ $t->id }})" class="text-amber-600 hover:underline">Suspendre</button>
                                    @endif
                                    @if($t->status !== 'archived')
                                        <button wire:click="archiveTenant({{ $t->id }})" class="text-red-600 hover:underline"
                                            onclick="return confirm('Archiver définitivement ?')">Archiver</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucun tenant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'domains')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Tenant</th>
                            <th class="px-4 py-2 text-left">Domaine</th>
                            <th class="px-4 py-2 text-left">Principal</th>
                            <th class="px-4 py-2 text-left">Vérifié</th>
                            <th class="px-4 py-2 text-left">SSL</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $d)
                            <tr>
                                <td class="px-4 py-2 text-xs">{{ $d->tenant?->name }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $d->domain }}</td>
                                <td class="px-4 py-2 text-xs">{{ $d->is_primary ? '✓' : '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $d->is_verified ? '✓' : '—' }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $d->ssl_status === 'ready',
                                        'bg-amber-100 text-amber-800' => $d->ssl_status === 'pending',
                                        'bg-red-100 text-red-800' => $d->ssl_status === 'failed',
                                    ])>{{ $d->ssl_status }}</span>
                                </td>
                                <td class="px-4 py-2 text-right text-xs">
                                    @if(! $d->is_verified)
                                        <button wire:click="verifyDomain({{ $d->id }})" class="text-emerald-600 hover:underline">Vérifier</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun domaine.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Tenant</th>
                            <th class="px-4 py-2 text-left">User</th>
                            <th class="px-4 py-2 text-left">Rôle</th>
                            <th class="px-4 py-2 text-left">Joined</th>
                            <th class="px-4 py-2 text-left">Active</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $tu)
                            <tr>
                                <td class="px-4 py-2 text-xs">{{ $tu->tenant?->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $tu->user?->email ?? $tu->user_id }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $tu->role }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($tu->joined_at)->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $tu->is_active ? '✓' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Aucun utilisateur.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
