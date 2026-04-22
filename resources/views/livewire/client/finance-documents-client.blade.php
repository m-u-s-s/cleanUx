<div class="space-y-6">
    <x-page-shell
        :eyebrow="__('Espace client')"
        :title="__('Documents & finance')"
        :subtitle="__('Consultez vos devis, vos factures, vos paiements récents et votre statut d’abonnement.')"
    >
        <x-slot name="actions">
            <a href="{{ route('client.dashboard') }}" class="cu-btn-secondary">{{ __('← Retour au dashboard') }}</a>
            <a href="{{ route('client.rendezvous.index') }}" class="cu-btn-secondary">{{ __('📅 Mes rendez-vous') }}</a>
            @if(! $subscriptionSummary['is_premium'])
                <a href="{{ route('premium.offer') }}" class="cu-btn-primary !bg-amber-500 hover:!bg-amber-600">{{ __('Découvrir Premium') }}</a>
            @endif
        </x-slot>
    </x-page-shell>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-kpi-card :title="__('Devis')" :value="$financeSummary['quotes_count']" tone="sky" icon="🧾" />
        <x-kpi-card :title="__('Devis à valider')" :value="$financeSummary['quotes_pending']" tone="amber" icon="⏳" />
        <x-kpi-card :title="__('Factures')" :value="$financeSummary['invoices_count']" tone="slate" icon="📄" />
        <x-kpi-card :title="__('En retard')" :value="$financeSummary['overdue_count']" tone="rose" icon="⚠️" />
        <x-kpi-card :title="__('Reste à payer')" :value="number_format((float) $financeSummary['outstanding_total'], 2, ',', ' ') . ' €'" tone="emerald" icon="💳" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-app-card padding="p-6" :title="__('Filtres')" :subtitle="__('Affinez les documents visibles.')">
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-slate-600">{{ __('Type de document') }}</label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button wire:click="setDocumentType('all')" class="cu-chip {{ $documentType === 'all' ? '!bg-slate-900 !text-white !border-slate-900' : '' }}">{{ __('Tous') }}</button>
                        <button wire:click="setDocumentType('quotes')" class="cu-chip {{ $documentType === 'quotes' ? '!bg-slate-900 !text-white !border-slate-900' : '' }}">{{ __('Devis') }}</button>
                        <button wire:click="setDocumentType('invoices')" class="cu-chip {{ $documentType === 'invoices' ? '!bg-slate-900 !text-white !border-slate-900' : '' }}">{{ __('Factures') }}</button>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-600">{{ __('Statut') }}</label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach (['all' => __('Tous'), 'draft' => __('Brouillon'), 'sent' => __('Envoyé'), 'accepted' => __('Accepté'), 'issued' => __('Émise'), 'partial' => __('Partiel'), 'paid' => __('Payée'), 'overdue' => __('En retard')] as $value => $label)
                            <button wire:click="setStatus('{{ $value }}')" class="cu-chip {{ $status === $value ? '!bg-slate-900 !text-white !border-slate-900' : '' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-app-card>

        <x-app-card padding="p-6" :title="__('Abonnement')" :subtitle="__('Suivi de votre plan et de ses avantages.')">
            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">{{ __('Plan') }}</span>
                    <span class="font-semibold text-slate-800">{{ ucfirst((string) $subscriptionSummary['plan_type']) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">{{ __('Statut') }}</span>
                    <span class="font-semibold {{ $subscriptionSummary['is_past_due'] ? 'text-rose-700' : ($subscriptionSummary['is_premium'] ? 'text-emerald-700' : 'text-slate-700') }}">
                        {{ $subscriptionSummary['is_premium'] ? __('Actif') : ucfirst((string) $subscriptionSummary['plan_status']) }}
                    </span>
                </div>
                @if($subscriptionSummary['renewal_at'])
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">{{ __('Renouvellement') }}</span>
                        <span class="font-semibold text-slate-800">{{ optional($subscriptionSummary['renewal_at'])->format('d/m/Y') }}</span>
                    </div>
                @endif
            </div>

            <div class="mt-5 cu-card-muted p-4 {{ $subscriptionSummary['is_premium'] ? '!border-amber-100 !bg-amber-50' : '' }}">
                <p class="text-sm font-semibold {{ $subscriptionSummary['is_premium'] ? 'text-amber-800' : 'text-slate-800' }}">{{ __('Avantages') }}</p>
                <ul class="mt-2 space-y-2 text-sm {{ $subscriptionSummary['is_premium'] ? 'text-amber-700' : 'text-slate-600' }}">
                    <li>{{ __('• Choix des employés favoris') }}</li>
                    <li>{{ __('• Meilleure visibilité sur les disponibilités') }}</li>
                    <li>{{ __('• Gestion plus simple de vos documents') }}</li>
                </ul>
            </div>
        </x-app-card>

        <x-app-card padding="p-6" :title="__('Paiements récents')" :subtitle="__('Derniers paiements enregistrés sur vos factures.')">
            <div class="space-y-3">
                @forelse($latestPaymentEvents as $payment)
                    <div class="cu-list-item flex items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-800">{{ $payment->payment_reference ?: __('Paiement') }}</p>
                            <p class="text-sm text-slate-500">{{ optional($payment->paid_at)->format('d/m/Y H:i') ?: optional($payment->created_at)->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-slate-900">{{ number_format((float) $payment->amount, 2, ',', ' ') }} €</p>
                            <p class="text-xs uppercase tracking-wide text-slate-500">{{ $payment->status }}</p>
                        </div>
                    </div>
                @empty
                    <x-empty-state :title="__('Aucun paiement récent')" :message="__('Vos paiements enregistrés apparaîtront ici.')" icon="💳" />
                @endforelse
            </div>
        </x-app-card>
    </div>

    @if($documentType !== 'invoices')
        <x-app-card padding="p-6" :title="__('Mes devis')" :subtitle="__('Consultez et téléchargez vos derniers devis.')">
            <div class="space-y-4">
                @forelse($quotes as $quote)
                    <div class="cu-list-item flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-lg font-semibold text-slate-900">{{ $quote->quote_number }}</p>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $this->quoteStatusBadge((string) $quote->status) }}">
                                    {{ ucfirst((string) $quote->status) }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ $quote->rendezVous?->service_display_name ?? 'Service non précisé' }}</p>
                            <p class="text-sm text-slate-500">{{ $quote->rendezVous?->serviceZone?->name ?? __('Zone non précisée') }} @if($quote->rendezVous?->organizationSite) · {{ $quote->rendezVous->organizationSite->name }} @endif</p>
                            <p class="text-sm text-slate-500">{{ __('Émis le') }} {{ optional($quote->issued_at)->format('d/m/Y') ?: '—' }} · {{ __('Valable jusqu’au') }} {{ optional($quote->valid_until)->format('d/m/Y') ?: '—' }}</p>
                        </div>

                        <div class="flex flex-col items-start gap-3 lg:items-end">
                            <p class="text-xl font-bold text-slate-900">{{ number_format((float) $quote->total_amount, 2, ',', ' ') }} €</p>
                            <a href="{{ route('client.finance.quote.download', $quote) }}" class="cu-btn-secondary">{{ __('📥 Télécharger le devis') }}</a>
                        </div>
                    </div>
                @empty
                    <x-empty-state :title="__('Aucun devis')" :message="__('Vos devis apparaîtront ici dès qu’un rendez-vous sera chiffré.')" icon="🧾" />
                @endforelse
            </div>
        </x-app-card>
    @endif

    @if($documentType !== 'quotes')
        <x-app-card padding="p-6" :title="__('Mes factures')" :subtitle="__('Suivez votre reste à payer et téléchargez vos factures.')">
            <div class="space-y-4">
                @forelse($invoices as $invoice)
                    <div class="cu-list-item flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-lg font-semibold text-slate-900">{{ $invoice->invoice_number }}</p>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $this->invoiceStatusBadge((string) $invoice->status) }}">
                                    {{ ucfirst((string) $invoice->status) }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ $invoice->rendezVous?->service_display_name ?? 'Service non précisé' }}</p>
                            <p class="text-sm text-slate-500">{{ __('Émise le') }} {{ optional($invoice->issued_at)->format('d/m/Y') ?: '—' }} · {{ __('Échéance') }} {{ optional($invoice->due_at)->format('d/m/Y') ?: '—' }}</p>
                            <p class="text-sm text-slate-500">{{ __('Reste à payer :') }} {{ number_format((float) $invoice->balance_due, 2, ',', ' ') }} €</p>
                        </div>

                        <div class="flex flex-col items-start gap-3 lg:items-end">
                            <p class="text-xl font-bold text-slate-900">{{ number_format((float) $invoice->total_amount, 2, ',', ' ') }} €</p>
                            <a href="{{ route('client.finance.invoice.download', $invoice) }}" class="cu-btn-secondary">{{ __('📥 Télécharger la facture') }}</a>
                        </div>
                    </div>
                @empty
                    <x-empty-state :title="__('Aucune facture')" :message="__('Vos factures apparaîtront ici dès qu’une prestation sera confirmée ou terminée.')" icon="📄" />
                @endforelse
            </div>
        </x-app-card>
    @endif
</div>
