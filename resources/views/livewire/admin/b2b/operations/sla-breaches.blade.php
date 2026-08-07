        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">SLA — manquements et escalades</h2>
                    <p class="text-sm text-slate-500">Événements SLA contractuels en dépassement ou escaladés.</p>
                </div>
                <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">{{ $slaBreaches->count() }}</span>
            </div>

            {{-- Récap SLA : compteurs globaux par statut (brio-kpi). Mobile-safe : 1 colonne < sm, 3 colonnes >= sm. --}}
            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="brio-kpi">
                    <p class="brio-kpi-label">En attente</p>
                    <p class="brio-kpi-value">{{ $this->slaCounts['pending'] }}</p>
                </div>
                <div class="brio-kpi">
                    <p class="brio-kpi-label">En dépassement</p>
                    <p class="brio-kpi-value text-amber-600">{{ $this->slaCounts['breached'] }}</p>
                </div>
                <div class="brio-kpi">
                    <p class="brio-kpi-label">Escaladés</p>
                    <p class="brio-kpi-value text-rose-600">{{ $this->slaCounts['escalated'] }}</p>
                </div>
            </div>

            @if($slaBreaches->isNotEmpty())
                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2">Mission</th>
                                <th class="px-4 py-2">Contrat</th>
                                <th class="px-4 py-2">Type</th>
                                <th class="px-4 py-2">Échéance</th>
                                <th class="px-4 py-2">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($slaBreaches as $event)
                                {{-- Les IDs mission/contrat restent affichés en texte : pas de route de détail dédiée à ce jour. --}}
                                <tr>
                                    <td class="px-4 py-2 text-slate-700">#{{ $event->mission_id }}</td>
                                    <td class="px-4 py-2 text-slate-700">#{{ $event->organization_contract_id }}</td>
                                    <td class="px-4 py-2 text-slate-700">{{ ucfirst($event->kind) }}</td>
                                    <td class="px-4 py-2 text-slate-500">{{ optional($event->due_at)->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-2">
                                        @php
                                            $chipClass = match ($event->status) {
                                                \App\Models\ContractSlaEvent::STATUS_ESCALATED => 'bg-rose-100 text-rose-700',
                                                \App\Models\ContractSlaEvent::STATUS_BREACHED => 'bg-amber-100 text-amber-700',
                                                \App\Models\ContractSlaEvent::STATUS_PENDING => 'bg-slate-100 text-slate-600',
                                                default => 'bg-slate-100 text-slate-600',
                                            };
                                        @endphp
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $chipClass }}">
                                            {{ strtoupper($event->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-slate-500">Aucun manquement SLA en cours. Tout est sous contrôle.</p>
            @endif
        </section>
