<div class="min-h-screen bg-slate-50 p-6">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Contrats partenaires</h1>
            <p class="text-sm text-slate-500">Vos contrats-cadres B2B, tarifs négociés et suivi SLA</p>
        </div>
    </div>

    @if ($contracts->isEmpty())
        <div class="flex flex-col items-center rounded-2xl border-2 border-dashed border-slate-200 bg-white py-16 text-center">
            <p class="text-lg font-bold text-slate-700">Aucun contrat</p>
            <p class="mt-1 text-sm text-slate-400">Aucun contrat-cadre n'est encore associé à votre organisation</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($contracts as $contract)
                @php
                    $statusConfig = [
                        'active'  => ['label' => 'Actif',     'bg' => 'bg-emerald-100 text-emerald-700'],
                        'signed'  => ['label' => 'Signé',      'bg' => 'bg-blue-100 text-blue-700'],
                        'pilot'   => ['label' => 'Pilote',     'bg' => 'bg-indigo-100 text-indigo-700'],
                        'draft'   => ['label' => 'Brouillon',  'bg' => 'bg-slate-100 text-slate-600'],
                        'expired' => ['label' => 'Expiré',     'bg' => 'bg-red-100 text-red-600'],
                    ];
                    $sc = $statusConfig[$contract->status] ?? ['label' => $contract->status, 'bg' => 'bg-slate-100 text-slate-600'];
                @endphp

                <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">

                    {{-- En-tête contrat --}}
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-900">
                                {{ $contract->contract_reference ?? 'Contrat #'.$contract->id }}
                            </p>
                            <p class="text-xs text-slate-500">
                                Partenaire : {{ $contract->providerOrganization?->name ?? '—' }}
                            </p>
                        </div>
                        <span class="flex-shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $sc['bg'] }}">
                            {{ $sc['label'] }}
                        </span>
                    </div>

                    {{-- Détails : remise / grille / fenêtre --}}
                    <div class="mt-3 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                        <div>
                            <p class="font-semibold text-slate-400">Remise négociée</p>
                            <p class="text-slate-700">
                                @if ($contract->negotiated_discount_percent)
                                    {{ rtrim(rtrim(number_format((float) $contract->negotiated_discount_percent, 2, ',', ' '), '0'), ',') }} %
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-400">Grille tarifaire</p>
                            <p class="text-slate-700">{{ $contract->rateCards->count() }} ligne(s)</p>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-400">Fenêtre</p>
                            <p class="text-slate-700">
                                {{ $contract->effective_from ? $contract->effective_from->format('d/m/Y') : '—' }}
                                →
                                {{ $contract->effective_to ? $contract->effective_to->format('d/m/Y') : '∞' }}
                            </p>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-400">SLA</p>
                            <p class="text-slate-700">
                                {{ $contract->sla_response_hours ? $contract->sla_response_hours.'h rép.' : '—' }}
                                @if ($contract->sla_resolution_hours)
                                    / {{ $contract->sla_resolution_hours }}h rés.
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Sous-bloc Work Orders + SLA breached --}}
                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                        <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                            {{ $contract->workOrders->count() }} ordre(s) de travail
                        </span>
                        @if (($contract->sla_breached_count ?? 0) > 0)
                            <span class="rounded-lg bg-red-100 px-2.5 py-1 text-[11px] font-semibold text-red-700">
                                {{ $contract->sla_breached_count }} SLA dépassé(s)
                            </span>
                        @else
                            <span class="rounded-lg bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">
                                SLA respecté
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
