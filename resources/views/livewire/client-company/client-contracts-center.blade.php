<div class="cu-page p-4 md:p-6">

    @php
        $statusConfig = [
            'active'  => ['label' => 'Actif',     'bg' => 'bg-emerald-100 text-emerald-700'],
            'signed'  => ['label' => 'Signé',      'bg' => 'bg-blue-100 text-blue-700'],
            'pilot'   => ['label' => 'Pilote',     'bg' => 'bg-indigo-100 text-indigo-700'],
            'draft'   => ['label' => 'Brouillon',  'bg' => 'bg-slate-100 text-slate-600'],
            'expired' => ['label' => 'Expiré',     'bg' => 'bg-red-100 text-red-600'],
        ];
    @endphp

    {{-- Page header --}}
    <div class="cu-page-header">
        <span class="cu-eyebrow">Espace société</span>
        <h1 class="mt-3 text-2xl font-black tracking-tight text-slate-900 md:text-3xl">Contrats partenaires</h1>
        <p class="mt-1 text-sm text-slate-500">Vos contrats-cadres B2B, tarifs négociés et suivi SLA — lecture seule.</p>
    </div>

    @if ($contracts->isEmpty())
        <div class="cu-empty">
            <div class="cu-empty-icon">📄</div>
            <p class="mt-4 text-lg font-bold text-slate-700">Aucun contrat</p>
            <p class="mt-1 text-sm text-slate-400">Aucun contrat-cadre n'est encore associé à votre organisation.</p>
        </div>
    @else
        {{-- Liste des contrats en cartes premium --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
            @foreach ($contracts as $contract)
                @php
                    $sc = $statusConfig[$contract->status] ?? ['label' => $contract->status, 'bg' => 'bg-slate-100 text-slate-600'];
                    $isSelected = $selectedContract && $selectedContract->id === $contract->id;
                @endphp

                <button
                    type="button"
                    wire:click="viewContract({{ $contract->id }})"
                    class="cu-card flex w-full flex-col gap-4 text-left {{ $isSelected ? 'ring-2 ring-sky-400' : '' }}"
                >
                    {{-- En-tête contrat --}}
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-slate-900">
                                {{ $contract->contract_reference ?? 'Contrat #'.$contract->id }}
                            </p>
                            <p class="mt-0.5 truncate text-xs text-slate-500">
                                Partenaire : {{ $contract->providerOrganization?->name ?? '—' }}
                            </p>
                        </div>
                        <span class="flex-shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $sc['bg'] }}">
                            {{ $sc['label'] }}
                        </span>
                    </div>

                    {{-- Métriques KPI : remise / grille / SLA --}}
                    <div class="grid grid-cols-3 gap-2">
                        <div class="cu-kpi !p-3">
                            <p class="cu-kpi-label !text-xs">Remise</p>
                            <p class="cu-kpi-value !mt-1 !text-lg">
                                @if ($contract->negotiated_discount_percent)
                                    {{ rtrim(rtrim(number_format((float) $contract->negotiated_discount_percent, 2, ',', ' '), '0'), ',') }}%
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <div class="cu-kpi !p-3">
                            <p class="cu-kpi-label !text-xs">Grille</p>
                            <p class="cu-kpi-value !mt-1 !text-lg">{{ $contract->rateCards->count() }}</p>
                            <p class="cu-kpi-hint !mt-0.5">ligne(s)</p>
                        </div>
                        <div class="cu-kpi !p-3">
                            <p class="cu-kpi-label !text-xs">SLA</p>
                            <p class="cu-kpi-value !mt-1 !text-lg">
                                {{ $contract->sla_response_hours ? $contract->sla_response_hours.'h' : '—' }}
                            </p>
                            <p class="cu-kpi-hint !mt-0.5">réponse</p>
                        </div>
                    </div>

                    {{-- Pied : work orders + SLA agrégé --}}
                    <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                        <span class="cu-inline-stat">
                            {{ $contract->workOrders->count() }} ordre(s)
                        </span>
                        @if (($contract->sla_breached_count ?? 0) > 0)
                            <span class="cu-inline-stat !border-red-200 !bg-red-50 !text-red-700">
                                <span class="cu-status-dot cu-status-dot-danger"></span>
                                {{ $contract->sla_breached_count }} SLA dépassé(s)
                            </span>
                        @else
                            <span class="cu-inline-stat !border-emerald-200 !bg-emerald-50 !text-emerald-700">
                                <span class="cu-status-dot cu-status-dot-success"></span>
                                SLA respecté
                            </span>
                        @endif
                    </div>
                </button>
            @endforeach
        </div>
    @endif

    {{-- Panneau détail --}}
    @if ($selectedContract)
        @php
            $detail = $selectedContract;
            $detailSc = $statusConfig[$detail->status] ?? ['label' => $detail->status, 'bg' => 'bg-slate-100 text-slate-600'];
            $breached = (int) ($detail->sla_breached_count ?? 0);
        @endphp

        <div class="cu-hero">
            {{-- En-tête panneau + fermer --}}
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="cu-section-title">
                            {{ $detail->contract_reference ?? 'Contrat #'.$detail->id }}
                        </h2>
                        <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $detailSc['bg'] }}">
                            {{ $detailSc['label'] }}
                        </span>
                    </div>
                    <p class="cu-section-subtitle">
                        Partenaire : {{ $detail->providerOrganization?->name ?? '—' }}
                        · {{ $detail->effective_from ? $detail->effective_from->format('d/m/Y') : '—' }}
                        → {{ $detail->effective_to ? $detail->effective_to->format('d/m/Y') : '∞' }}
                    </p>
                </div>
                <button type="button" wire:click="closeContract" class="cu-btn-secondary">
                    Fermer
                </button>
            </div>

            {{-- Statut SLA agrégé --}}
            <div class="mt-5">
                @if ($breached > 0)
                    <div class="cu-note !border-red-200 !bg-red-50 !text-red-800">
                        <span class="font-bold">{{ $breached }} SLA dépassé(s)</span> sur ce contrat
                        ({{ $detail->sla_response_hours ? $detail->sla_response_hours.'h réponse' : '—' }}
                        @if ($detail->sla_resolution_hours) / {{ $detail->sla_resolution_hours }}h résolution @endif).
                    </div>
                @else
                    <div class="cu-note !border-emerald-200 !bg-emerald-50 !text-emerald-800">
                        <span class="font-bold">SLA respecté.</span>
                        {{ $detail->sla_response_hours ? $detail->sla_response_hours.'h réponse' : '—' }}
                        @if ($detail->sla_resolution_hours) / {{ $detail->sla_resolution_hours }}h résolution @endif
                    </div>
                @endif
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
                {{-- Work orders --}}
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-[0.14em] text-slate-500">
                        Ordres de travail ({{ $detail->workOrders->count() }})
                    </h3>
                    @if ($detail->workOrders->isEmpty())
                        <p class="mt-3 text-sm text-slate-400">Aucun ordre de travail rattaché.</p>
                    @else
                        <div class="mt-3 space-y-2">
                            @foreach ($detail->workOrders as $wo)
                                <div class="cu-list-item flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800">
                                            {{ $wo->title ?? $wo->reference ?? 'OT #'.$wo->id }}
                                        </p>
                                        @if ($wo->reference && $wo->title)
                                            <p class="truncate text-xs text-slate-400">{{ $wo->reference }}</p>
                                        @endif
                                    </div>
                                    <span class="cu-chip flex-shrink-0">{{ $wo->status ?? '—' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Grille tarifaire --}}
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-[0.14em] text-slate-500">
                        Grille tarifaire ({{ $detail->rateCards->count() }})
                    </h3>
                    @if ($detail->rateCards->isEmpty())
                        <p class="mt-3 text-sm text-slate-400">Aucune ligne tarifaire négociée.</p>
                    @else
                        <x-ui.table-shell class="mt-3">
                            <table class="cu-table w-full min-w-[24rem]">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th class="text-right">Prix négocié</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($detail->rateCards as $rc)
                                        <tr>
                                            <td>{{ $rc->serviceCatalog?->name ?? 'Service #'.$rc->service_catalog_id }}</td>
                                            <td class="text-right font-semibold text-slate-800">
                                                @if (! is_null($rc->negotiated_unit_price_cents))
                                                    {{ number_format($rc->negotiated_unit_price_cents / 100, 2, ',', ' ') }}
                                                    {{ $rc->currency ?? 'EUR' }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-ui.table-shell>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
