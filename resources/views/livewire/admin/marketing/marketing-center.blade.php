<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Marketing v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Marketing automation</h1>
                <p class="text-sm text-slate-500">Segments + Campagnes multi-canal (email / sms / push) avec opt-out RGPD</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Segments actifs</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['segments_active']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Campagnes en cours</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['campaigns_running']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Envoyés 7j</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['sent_7d']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Opt-outs total</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['opt_outs_total']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['segments' => 'Segments', 'campaigns' => 'Campagnes', 'recipients' => 'Recipients'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Recherche..." class="w-full rounded-xl border-gray-300 text-sm" />

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'segments')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-right">Membres</th>
                            <th class="px-4 py-2 text-left">Dernier compute</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $s)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $s->code }}</td>
                                <td class="px-4 py-2 text-xs font-semibold">{{ $s->name }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($s->member_count) }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $s->last_computed_at?->diffForHumans() ?? '—' }}</td>
                                <td class="px-4 py-2 text-right text-xs">
                                    <button wire:click="recomputeSegment({{ $s->id }})" class="font-semibold text-indigo-600 hover:underline">Recalculer</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Aucun segment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'campaigns')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-left">Type</th>
                            <th class="px-4 py-2 text-left">Segment</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $c)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $c->code }}</td>
                                <td class="px-4 py-2 text-xs font-semibold">{{ $c->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $c->type }}</td>
                                <td class="px-4 py-2 text-xs">{{ $c->segment?->name ?? '—' }}</td>
                                <td class="px-4 py-2"><span class="text-xs font-semibold">{{ $c->status }}</span></td>
                                <td class="px-4 py-2 text-right text-xs">
                                    @if($c->status === 'draft')
                                        <button wire:click="scheduleCampaign({{ $c->id }})" class="font-semibold text-emerald-600 hover:underline mr-2">Planifier</button>
                                    @endif
                                    @if(in_array($c->status, ['running', 'scheduled']))
                                        <button wire:click="pauseCampaign({{ $c->id }})" class="font-semibold text-amber-600 hover:underline mr-2">Pause</button>
                                        <button wire:click="cancelCampaign({{ $c->id }})" class="font-semibold text-red-600 hover:underline">Annuler</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucune campagne.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Campagne</th>
                            <th class="px-4 py-2 text-left">User</th>
                            <th class="px-4 py-2 text-left">Channel</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-left">Variant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $r)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $r->scheduled_for?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $r->campaign?->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $r->user?->email }}</td>
                                <td class="px-4 py-2 text-xs">{{ $r->channel }}</td>
                                <td class="px-4 py-2">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => in_array($r->status, ['sent', 'delivered', 'opened', 'clicked']),
                                        'bg-indigo-100 text-indigo-800' => $r->status === 'queued',
                                        'bg-red-100 text-red-800' => $r->status === 'failed',
                                        'bg-amber-100 text-amber-800' => in_array($r->status, ['opted_out', 'skipped']),
                                    ])>{{ $r->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs">{{ $r->variant_label ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun recipient.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
