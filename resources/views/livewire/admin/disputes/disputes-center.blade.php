<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Disputes / SAV v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre de gestion des litiges</h1>
                <p class="text-sm text-slate-500">SLA, escalades, résolutions financières — workflow complet.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                ← Dashboard
            </a>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Disputes ouvertes</p>
                <p class="text-2xl font-black text-slate-900">{{ $kpis['open'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En retard SLA</p>
                <p class="text-2xl font-black text-red-600">{{ $kpis['overdue'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Escaladées</p>
                <p class="text-2xl font-black text-amber-600">{{ $kpis['escalated'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Résolues aujourd'hui</p>
                <p class="text-2xl font-black text-emerald-600">{{ $kpis['resolved_today'] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 {{ $selected ? 'lg:grid-cols-2' : '' }} gap-6">
            {{-- Liste --}}
            <div class="rounded-2xl border bg-white shadow-sm">
                <div class="p-4 flex flex-wrap gap-2 border-b">
                    <input type="text" wire:model.live.debounce.300ms="search"
                           placeholder="Rechercher..."
                           class="flex-1 rounded-xl border-gray-300 text-sm" />
                    <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                        <option value="">Tous statuts</option>
                        <option value="open">Ouvert</option>
                        <option value="assigned">Assigné</option>
                        <option value="awaiting_client">Attente client</option>
                        <option value="awaiting_provider">Attente provider</option>
                        <option value="investigating">Investigation</option>
                        <option value="escalated">Escaladé</option>
                        <option value="resolved">Résolu</option>
                        <option value="closed">Clos</option>
                    </select>
                    <select wire:model.live="filterPriority" class="rounded-xl border-gray-300 text-sm">
                        <option value="">Toutes priorités</option>
                        <option value="urgent">Urgent</option>
                        <option value="high">Haute</option>
                        <option value="normal">Normale</option>
                        <option value="low">Basse</option>
                    </select>
                    <select wire:model.live="filterCategory" class="rounded-xl border-gray-300 text-sm">
                        <option value="">Toutes catégories</option>
                        <option value="quality">Qualité</option>
                        <option value="no_show">No-show</option>
                        <option value="payment">Paiement</option>
                        <option value="damage">Dommage</option>
                        <option value="safety">Sécurité</option>
                        <option value="communication">Communication</option>
                        <option value="other">Autre</option>
                    </select>
                    <label class="flex items-center gap-2 text-xs">
                        <input type="checkbox" wire:model.live="showOverdueOnly" />
                        En retard
                    </label>
                </div>

                <div class="divide-y">
                    @forelse($list as $case)
                        <button wire:click="select({{ $case->id }})"
                                class="w-full text-left p-4 hover:bg-slate-50 {{ $selectedId === $case->id ? 'bg-indigo-50' : '' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs text-slate-500">{{ $case->reference ?? '#'.$case->id }}</span>
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold',
                                            'bg-red-100 text-red-800' => $case->priority === 'urgent',
                                            'bg-amber-100 text-amber-800' => $case->priority === 'high',
                                            'bg-slate-100 text-slate-700' => in_array($case->priority, ['normal','low']),
                                        ])>{{ $case->priority }}</span>
                                        @if($case->is_overdue)
                                            <span class="text-xs text-red-600 font-bold">⏰ retard</span>
                                        @endif
                                    </div>
                                    <p class="font-bold text-slate-900 mt-1">{{ $case->subject }}</p>
                                    <p class="text-xs text-slate-500 mt-1">
                                        {{ $case->client?->name ?? '—' }}
                                        @if($case->provider) → {{ $case->provider->name }} @endif
                                        · {{ $case->category }}
                                        @if($case->assignee) · 👤 {{ $case->assignee->name }} @endif
                                    </p>
                                </div>
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $case->status === 'resolved',
                                    'bg-slate-100 text-slate-700' => in_array($case->status, ['closed','open']),
                                    'bg-indigo-100 text-indigo-800' => in_array($case->status, ['assigned','investigating']),
                                    'bg-amber-100 text-amber-800' => in_array($case->status, ['awaiting_client','awaiting_provider']),
                                    'bg-red-100 text-red-800' => $case->status === 'escalated',
                                ])>{{ $case->status }}</span>
                            </div>
                        </button>
                    @empty
                        <div class="p-10 text-center text-slate-400">Aucune dispute ne correspond.</div>
                    @endforelse
                </div>

                <div class="p-3">{{ $list->links() }}</div>
            </div>

            {{-- Détail dispute --}}
            @if($selected)
                <div class="rounded-2xl border bg-white shadow-sm">
                    <div class="p-4 border-b flex items-start justify-between">
                        <div>
                            <p class="font-mono text-xs text-slate-500">{{ $selected->reference }}</p>
                            <h2 class="text-lg font-black text-slate-900 mt-1">{{ $selected->subject }}</h2>
                            <p class="text-xs text-slate-500 mt-1">
                                {{ $selected->client?->name }}
                                @if($selected->provider) → {{ $selected->provider->name }} @endif
                                · {{ $selected->category }} · {{ $selected->severity }}
                            </p>
                        </div>
                        <button wire:click="closeDetail" class="text-slate-400 hover:text-slate-700">✕</button>
                    </div>

                    {{-- Actions rapides --}}
                    <div class="p-4 border-b flex flex-wrap gap-2">
                        @if(! $selected->assigned_to)
                            <button wire:click="assignToMe"
                                    class="rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white">
                                M'assigner
                            </button>
                        @endif
                        @if(! in_array($selected->status, ['resolved','closed']))
                            <button wire:click="transitionTo('investigating')"
                                    class="rounded-xl border px-3 py-1.5 text-xs font-semibold">Investigation</button>
                            <button wire:click="transitionTo('awaiting_client')"
                                    class="rounded-xl border px-3 py-1.5 text-xs font-semibold">Attente client</button>
                            <button wire:click="transitionTo('awaiting_provider')"
                                    class="rounded-xl border px-3 py-1.5 text-xs font-semibold">Attente provider</button>
                            <button wire:click="escalate"
                                    class="rounded-xl bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white">Escalader</button>
                            <button wire:click="transitionTo('closed')"
                                    class="rounded-xl border border-red-300 text-red-700 px-3 py-1.5 text-xs font-semibold">Clore</button>
                        @endif
                    </div>

                    {{-- Timeline --}}
                    <div class="p-4 max-h-96 overflow-y-auto space-y-3 border-b">
                        @forelse($selected->events as $event)
                            <div @class([
                                'rounded-xl p-3 border',
                                'bg-slate-50 border-slate-200' => $event->author_role === 'system',
                                'bg-indigo-50 border-indigo-200' => $event->author_role === 'admin',
                                'bg-emerald-50 border-emerald-200' => $event->author_role === 'client',
                                'bg-amber-50 border-amber-200' => $event->author_role === 'provider',
                            ])>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-semibold">
                                        @if($event->author_role === 'system')
                                            ⚙ Système
                                        @else
                                            {{ ucfirst($event->author_role) }} : {{ $event->author?->name ?? '—' }}
                                        @endif
                                    </span>
                                    <span class="text-slate-500">{{ $event->created_at->format('d/m H:i') }}</span>
                                </div>
                                <p class="text-sm text-slate-700 mt-1">
                                    @if($event->type === 'status_changed')
                                        Statut : <span class="font-mono">{{ $event->from_status }}</span> → <span class="font-mono">{{ $event->to_status }}</span>
                                        @if($event->body) — {{ $event->body }} @endif
                                    @else
                                        {{ $event->body }}
                                    @endif
                                </p>
                                @if($event->visibility !== 'all')
                                    <p class="text-xs text-slate-400 mt-1">Visibilité : {{ $event->visibility }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-slate-400 text-center">Aucun événement.</p>
                        @endforelse
                    </div>

                    {{-- Add message --}}
                    @if(! in_array($selected->status, ['resolved','closed']))
                        <div class="p-4 border-b space-y-2">
                            <h3 class="text-sm font-bold">Ajouter un message</h3>
                            <textarea wire:model="messageBody" rows="2"
                                      class="w-full rounded-xl border-gray-300 text-sm"
                                      placeholder="Message admin..."></textarea>
                            @error('messageBody') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            <div class="flex gap-2 items-center">
                                <select wire:model="messageVisibility" class="rounded-xl border-gray-300 text-xs">
                                    <option value="all">Visible client + provider</option>
                                    <option value="client">Visible client uniquement</option>
                                    <option value="provider">Visible provider uniquement</option>
                                    <option value="private">Note privée (admin uniquement)</option>
                                </select>
                                <button wire:click="postMessage"
                                        class="rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white">
                                    Envoyer
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- Résolution --}}
                    @if(! in_array($selected->status, ['resolved','closed']))
                        <div class="p-4 space-y-2">
                            <h3 class="text-sm font-bold">Appliquer une résolution</h3>
                            <select wire:model.live="resolutionType" class="w-full rounded-xl border-gray-300 text-sm">
                                <option value="refund_full">Refund total Stripe</option>
                                <option value="refund_partial">Refund partiel Stripe</option>
                                <option value="credit">Crédit client (promo code)</option>
                                <option value="replacement_booking">Booking de remplacement</option>
                                <option value="provider_warning">Avertissement provider</option>
                                <option value="provider_sanction">Sanction provider</option>
                                <option value="no_action">Pas d'action</option>
                                <option value="dismissed">Rejeté</option>
                            </select>

                            @if(in_array($resolutionType, ['refund_partial','credit']))
                                <input type="number" step="0.01" min="0.01"
                                       wire:model="resolutionAmount"
                                       placeholder="Montant"
                                       class="w-full rounded-xl border-gray-300 text-sm" />
                                @error('resolutionAmount') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            @endif

                            <textarea wire:model="resolutionExplanation" rows="2"
                                      class="w-full rounded-xl border-gray-300 text-sm"
                                      placeholder="Explication visible au client..."></textarea>
                            @error('resolutionExplanation') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <button wire:click="applyResolution"
                                    wire:confirm="Appliquer cette résolution ?"
                                    class="w-full rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">
                                Appliquer & clôturer
                            </button>
                        </div>
                    @else
                        <div class="p-4">
                            @forelse($selected->resolutions as $r)
                                <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 mb-2">
                                    <p class="text-xs uppercase font-bold text-emerald-800">{{ $r->resolution_type }}</p>
                                    <p class="text-sm font-bold mt-1">
                                        @if($r->amount) {{ number_format((float)$r->amount, 2, ',', ' ') }} {{ $r->currency }} @endif
                                    </p>
                                    @if($r->explanation)
                                        <p class="text-sm text-slate-700 mt-1">{{ $r->explanation }}</p>
                                    @endif
                                    <p class="text-xs text-slate-500 mt-1">
                                        {{ $r->status }} · {{ optional($r->applied_at)->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                            @empty
                                <p class="text-sm text-slate-500 text-center">Aucune résolution enregistrée.</p>
                            @endforelse
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
