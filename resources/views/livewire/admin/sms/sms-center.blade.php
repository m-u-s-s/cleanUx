<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">SMS v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre SMS / WhatsApp</h1>
                <p class="text-sm text-slate-500">Provider : <code class="font-mono">{{ config('sms.default_provider') }}</code>
                    @if(config('sms.enabled'))
                        <span class="text-emerald-600 font-semibold">activé</span>
                    @else
                        <span class="text-red-600 font-semibold">désactivé</span>
                    @endif
                </p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Envois 24h</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['total_24h']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Délivrés 24h</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['delivered_24h']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Échec 24h</p>
                <p class="text-2xl font-black text-red-600">{{ number_format($kpis['failed_24h']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Rate-limited 24h</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['rate_limited_24h']) }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Téléphone, corps, external_id, user..."
                   class="flex-1 rounded-xl border-gray-300 text-sm" />
            <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                <option value="">Tous statuts</option>
                <option value="queued">Queued</option>
                <option value="sent">Sent</option>
                <option value="delivered">Delivered</option>
                <option value="failed">Failed</option>
                <option value="undelivered">Undelivered</option>
                <option value="rate_limited">Rate limited</option>
                <option value="rejected">Rejected</option>
            </select>
            <select wire:model.live="filterProvider" class="rounded-xl border-gray-300 text-sm">
                <option value="">Tous providers</option>
                <option value="mock">Mock</option>
                <option value="twilio">Twilio</option>
                <option value="vonage">Vonage</option>
            </select>
            <select wire:model.live="filterCategory" class="rounded-xl border-gray-300 text-sm">
                <option value="">Toutes catégories</option>
                <option value="transactional">Transactional</option>
                <option value="verification">Verification</option>
                <option value="reminder">Reminder</option>
                <option value="marketing">Marketing</option>
            </select>
        </div>

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-left">Provider</th>
                        <th class="px-4 py-2 text-left">Téléphone</th>
                        <th class="px-4 py-2 text-left">Catégorie</th>
                        <th class="px-4 py-2 text-left">Corps</th>
                        <th class="px-4 py-2 text-left">Statut</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($items as $m)
                        <tr>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ $m->queued_at?->format('d/m H:i') }}</td>
                            <td class="px-4 py-2 text-xs font-mono">{{ $m->provider }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $m->to_phone }}</td>
                            <td class="px-4 py-2 text-xs">{{ $m->category }}</td>
                            <td class="px-4 py-2 text-xs text-slate-700 max-w-xs truncate" title="{{ $m->body }}">
                                {{ \Illuminate\Support\Str::limit($m->body, 60) }}
                            </td>
                            <td class="px-4 py-2">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $m->status === 'delivered',
                                    'bg-indigo-100 text-indigo-800' => in_array($m->status, ['queued','sent']),
                                    'bg-red-100 text-red-800' => in_array($m->status, ['failed','undelivered','rejected']),
                                    'bg-amber-100 text-amber-800' => $m->status === 'rate_limited',
                                ])>{{ $m->status }}</span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                @if(in_array($m->status, ['failed','undelivered','rate_limited']))
                                    <button wire:click="retry({{ $m->id }})"
                                            class="text-xs font-semibold text-indigo-600 hover:underline">
                                        Retry
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucun SMS.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
