<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">KYC v2</p>
                <h1 class="text-2xl font-black text-slate-900">Vérifications d'identité</h1>
                <p class="text-sm text-slate-500">Background checks providers (Onfido / Veriff / Mock) — workflow + override admin.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Total</p>
                <p class="text-2xl font-black text-slate-900">{{ $kpis['total'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En cours</p>
                <p class="text-2xl font-black text-indigo-600">{{ $kpis['pending'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">À review</p>
                <p class="text-2xl font-black text-amber-600">{{ $kpis['requiring_review'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Approuvés</p>
                <p class="text-2xl font-black text-emerald-600">{{ $kpis['approved'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Rejetés</p>
                <p class="text-2xl font-black text-red-600">{{ $kpis['rejected'] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 {{ $selected ? 'lg:grid-cols-2' : '' }} gap-6">
            <div class="rounded-2xl border bg-white shadow-sm">
                <div class="p-3 flex flex-wrap gap-2 border-b">
                    <input type="text" wire:model.live.debounce.300ms="search"
                           placeholder="Rechercher..."
                           class="flex-1 rounded-xl border-gray-300 text-sm" />
                    <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                        <option value="">Tous statuts</option>
                        <option value="pending">Pending</option>
                        <option value="in_review">In review</option>
                        <option value="awaiting_documents">Awaiting docs</option>
                        <option value="clear">Clear</option>
                        <option value="consider">Consider</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <select wire:model.live="filterDecision" class="rounded-xl border-gray-300 text-sm">
                        <option value="">Toutes décisions</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="manual_review">Manual review</option>
                    </select>
                    <select wire:model.live="filterProvider" class="rounded-xl border-gray-300 text-sm">
                        <option value="">Tous providers</option>
                        <option value="mock">Mock</option>
                        <option value="onfido">Onfido</option>
                        <option value="veriff">Veriff</option>
                        <option value="sumsub">Sum&Sub</option>
                    </select>
                </div>

                <div class="divide-y">
                    @forelse($list as $v)
                        <button wire:click="select({{ $v->id }})"
                                class="w-full text-left p-4 hover:bg-slate-50 {{ $selectedId === $v->id ? 'bg-indigo-50' : '' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="font-bold text-slate-900">{{ $v->user?->name ?? '—' }}</p>
                                        <span class="text-xs font-mono text-slate-400">{{ $v->provider }}</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-1">
                                        {{ $v->user?->email }}
                                        @if($v->country_code) · {{ $v->country_code }} @endif
                                        @if($v->score !== null) · score {{ number_format((float) $v->score, 2) }} @endif
                                    </p>
                                </div>
                                <div class="flex flex-col items-end gap-1">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $v->decision === 'approved',
                                        'bg-amber-100 text-amber-800' => $v->decision === 'manual_review',
                                        'bg-red-100 text-red-800' => $v->decision === 'rejected',
                                        'bg-slate-100 text-slate-700' => $v->decision === 'pending',
                                    ])>{{ $v->decision }}</span>
                                    <span class="text-xs text-slate-400">{{ $v->status }}</span>
                                </div>
                            </div>
                        </button>
                    @empty
                        <div class="p-10 text-center text-slate-400">Aucune vérification.</div>
                    @endforelse
                </div>

                <div class="p-3">{{ $list->links() }}</div>
            </div>

            @if($selected)
                <div class="rounded-2xl border bg-white shadow-sm">
                    <div class="p-4 border-b flex items-start justify-between">
                        <div>
                            <p class="text-xs font-mono text-slate-500">{{ $selected->external_check_id ?? $selected->external_applicant_id }}</p>
                            <h2 class="text-lg font-black text-slate-900 mt-1">{{ $selected->user?->name }}</h2>
                            <p class="text-xs text-slate-500 mt-1">
                                {{ $selected->user?->email }} · {{ $selected->provider }} · {{ $selected->country_code }}
                            </p>
                        </div>
                        <button wire:click="closeDetail" class="text-slate-400 hover:text-slate-700">✕</button>
                    </div>

                    <div class="p-4 border-b grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs uppercase font-bold text-slate-500">Status</p>
                            <p class="font-bold">{{ $selected->status }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase font-bold text-slate-500">Decision</p>
                            <p class="font-bold">{{ $selected->decision }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase font-bold text-slate-500">Score</p>
                            <p class="font-bold">{{ $selected->score !== null ? number_format((float) $selected->score, 2) : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase font-bold text-slate-500">Démarré</p>
                            <p class="text-sm">{{ optional($selected->started_at)->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>

                    <div class="p-4 border-b">
                        <h3 class="text-sm font-bold mb-2">Checks individuels</h3>
                        @forelse($selected->checks as $check)
                            <div class="flex items-center justify-between py-1.5 border-b last:border-0">
                                <span class="text-sm font-semibold">{{ $check->check_type }}</span>
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $check->result === 'clear',
                                    'bg-amber-100 text-amber-800' => $check->result === 'consider',
                                    'bg-red-100 text-red-800' => $check->result === 'rejected',
                                    'bg-slate-100 text-slate-700' => in_array($check->result, ['pending','unidentified','caution']),
                                ])>{{ $check->result }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">Aucun check enregistré.</p>
                        @endforelse
                    </div>

                    @if($selected->rejection_reason)
                        <div class="p-4 border-b">
                            <p class="text-xs uppercase font-bold text-red-600">Raison du rejet</p>
                            <p class="text-sm text-slate-700 mt-1">{{ $selected->rejection_reason }}</p>
                        </div>
                    @endif

                    <div class="p-4 space-y-3">
                        @if(! in_array($selected->status, ['clear', 'rejected', 'cancelled', 'expired']))
                            <button wire:click="syncStatus"
                                    class="w-full rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">
                                Synchroniser avec {{ $selected->provider }}
                            </button>
                        @endif

                        @if($selected->decision !== 'approved')
                            <div class="border-t pt-3">
                                <label class="text-xs font-bold uppercase text-slate-500">Note (optionnel)</label>
                                <input type="text" wire:model="manualNote"
                                       class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                                <button wire:click="approve"
                                        wire:confirm="Approuver manuellement cette vérification ?"
                                        class="mt-2 w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                    Approuver manuellement
                                </button>
                            </div>
                        @endif

                        @if($selected->decision !== 'rejected')
                            <div class="border-t pt-3">
                                <label class="text-xs font-bold uppercase text-slate-500">Raison du rejet</label>
                                <textarea wire:model="manualReason" rows="2"
                                          class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                                          placeholder="Raison visible par le provider..."></textarea>
                                @error('manualReason') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                <button wire:click="reject"
                                        wire:confirm="Rejeter cette vérification ?"
                                        class="mt-2 w-full rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                                    Rejeter
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
