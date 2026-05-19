<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Audit v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Audit / Observability</h1>
                <p class="text-sm text-slate-500">Events typés + redaction PII + retention auto</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Events 24h</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['events_24h']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Critical 24h</p>
                <p class="text-2xl font-black text-red-600">{{ number_format($kpis['critical_24h']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Errors 24h</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['errors_24h']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Pinned</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['pinned_total']) }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Actor email, subject, event_type..."
                   class="flex-1 rounded-xl border-gray-300 text-sm" />
            <input type="text" wire:model.live.debounce.300ms="filterEventType"
                   placeholder="Event type contains..."
                   class="rounded-xl border-gray-300 text-sm" />
            <select wire:model.live="filterDomain" class="rounded-xl border-gray-300 text-sm">
                <option value="">Tous domaines</option>
                <option value="auth">Auth</option>
                <option value="security">Security</option>
                <option value="finance">Finance</option>
                <option value="payment">Payment</option>
                <option value="gdpr">GDPR</option>
                <option value="kyc">KYC</option>
                <option value="risk">Risk</option>
                <option value="audit">Audit</option>
                <option value="booking">Booking</option>
                <option value="sms">SMS</option>
                <option value="push">Push</option>
                <option value="marketing">Marketing</option>
                <option value="insurance">Insurance</option>
                <option value="fx">FX</option>
                <option value="general">General</option>
            </select>
            <select wire:model.live="filterSeverity" class="rounded-xl border-gray-300 text-sm">
                <option value="">Toutes severities</option>
                <option value="info">Info</option>
                <option value="warning">Warning</option>
                <option value="error">Error</option>
                <option value="critical">Critical</option>
            </select>
            <label class="flex items-center gap-2 px-3 text-xs font-semibold text-slate-700">
                <input type="checkbox" wire:model.live="pinnedOnly" /> Pinned only
            </label>
        </div>

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-left">Event</th>
                        <th class="px-4 py-2 text-left">Domain</th>
                        <th class="px-4 py-2 text-left">Severity</th>
                        <th class="px-4 py-2 text-left">Actor</th>
                        <th class="px-4 py-2 text-left">Subject</th>
                        <th class="px-4 py-2 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($items as $e)
                        <tr>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ $e->occurred_at?->format('d/m H:i:s') }}</td>
                            <td class="px-4 py-2 text-xs font-mono">{{ $e->event_type }}</td>
                            <td class="px-4 py-2 text-xs">{{ $e->domain }}</td>
                            <td class="px-4 py-2">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-slate-100 text-slate-700' => $e->severity === 'info',
                                    'bg-amber-100 text-amber-800' => $e->severity === 'warning',
                                    'bg-red-100 text-red-800' => $e->severity === 'error',
                                    'bg-purple-100 text-purple-800' => $e->severity === 'critical',
                                ])>{{ $e->severity }}</span>
                            </td>
                            <td class="px-4 py-2 text-xs">{{ $e->actor_label ?? $e->actor_type }}</td>
                            <td class="px-4 py-2 text-xs">
                                @if($e->subject_type)
                                    <span class="font-mono">{{ $e->subject_type }}#{{ $e->subject_id }}</span>
                                    @if($e->subject_label)
                                        <span class="text-slate-500">— {{ $e->subject_label }}</span>
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right text-xs">
                                <button wire:click="togglePin({{ $e->id }})" class="text-indigo-600 hover:underline">
                                    {{ $e->is_pinned ? 'Unpin' : 'Pin' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucun event audit.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
