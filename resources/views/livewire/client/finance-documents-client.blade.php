<div data-component-root="client-finance-documents">
<div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-7xl space-y-8 px-4 pb-10 pt-6 sm:px-6 lg:px-8">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 text-white shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.35fr_0.75fr] lg:px-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-300">
                        Espace finance client
                    </p>

                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                        {{ __('Documents & finance') }}
                    </h1>

                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200 sm:text-base">
                        Retrouvez vos devis, factures, paiements récents, statut d’abonnement et reste à payer dans une vue claire.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('client.dashboard') }}"
                           class="rounded-2xl bg-white px-4 py-2 text-sm font-black text-slate-900 transition hover:bg-slate-100">
                            ← Retour dashboard
                        </a>

                        <a href="{{ route('client.rendezvous.index') }}"
                           class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                            📅 Mes rendez-vous
                        </a>

                        @if(! $subscriptionSummary['is_premium'])
                            <a href="{{ route('premium.offer') }}"
                               class="rounded-2xl bg-amber-400 px-4 py-2 text-sm font-black text-slate-900 transition hover:bg-amber-300">
                                ★ Découvrir Premium
                            </a>
                        @endif
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">
                                Santé paiement
                            </p>

                            <h2 class="mt-2 text-xl font-black text-white">
                                {{ $paymentHealth['title'] }}
                            </h2>

                            <p class="mt-2 text-sm text-slate-200">
                                {{ $paymentHealth['message'] }}
                            </p>
                        </div>

                        <span class="rounded-full px-3 py-1 text-xs font-black
                            {{ $paymentHealth['tone'] === 'rose' ? 'bg-rose-400 text-white' : '' }}
                            {{ $paymentHealth['tone'] === 'amber' ? 'bg-amber-300 text-slate-900' : '' }}
                            {{ $paymentHealth['tone'] === 'emerald' ? 'bg-emerald-300 text-slate-900' : '' }}">
                            {{ $paymentHealth['label'] }}
                        </span>
                    </div>

                    <div class="mt-5 rounded-2xl bg-white/10 p-4">
                        <p class="text-sm text-slate-300">Reste à payer</p>
                        <p class="mt-1 text-3xl font-black text-white">
                            {{ number_format((float) $financeSummary['outstanding_total'], 2, ',', ' ') }}
                            {{ $financeSummary['currency_symbol'] ?? '€' }}
                        </p>

                        @if($financeSummary['next_due_at'])
                            <p class="mt-1 text-xs text-slate-300">
                                Prochaine échéance : {{ optional($financeSummary['next_due_at'])->format('d/m/Y') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-kpi-card :title="__('Devis')" :value="$financeSummary['quotes_count']" tone="sky" icon="🧾" />
            <x-kpi-card :title="__('À valider')" :value="$financeSummary['quotes_pending']" tone="amber" icon="⏳" />
            <x-kpi-card :title="__('Factures')" :value="$financeSummary['invoices_count']" tone="slate" icon="📄" />
            <x-kpi-card :title="__('En retard')" :value="$financeSummary['overdue_count']" tone="rose" icon="⚠️" />
            <x-kpi-card
                :title="__('Reste à payer')"
                :value="number_format((float) $financeSummary['outstanding_total'], 2, ',', ' ') . ' ' . ($financeSummary['currency_symbol'] ?? '€')"
                tone="emerald"
                icon="💳"
            />
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_0.8fr]">
            <x-app-card padding="p-6" :title="__('Pilotage des documents')" :subtitle="__('Filtrez rapidement vos devis et factures.')">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Recherche
                        </label>

                        <input
                            type="text"
                            wire:model.live.debounce.350ms="search"
                            placeholder="Numéro, service, ville, adresse, référence…"
                            class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                        >
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Tri
                        </label>

                        <select
                            wire:model.live="sort"
                            class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            @foreach($sortOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <div>
                        <p class="text-sm font-semibold text-slate-700">Type de document</p>

                        <div class="mt-2 flex flex-wrap gap-2">
                            <button wire:click="setDocumentType('all')" class="cu-chip {{ $documentType === 'all' ? '!border-slate-900 !bg-slate-900 !text-white' : '' }}">
                                Tous
                            </button>

                            <button wire:click="setDocumentType('quotes')" class="cu-chip {{ $documentType === 'quotes' ? '!border-slate-900 !bg-slate-900 !text-white' : '' }}">
                                Devis
                            </button>

                            <button wire:click="setDocumentType('invoices')" class="cu-chip {{ $documentType === 'invoices' ? '!border-slate-900 !bg-slate-900 !text-white' : '' }}">
                                Factures
                            </button>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-slate-700">Statut</p>

                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach($statusOptions as $value => $label)
                                <button wire:click="setStatus('{{ $value }}')" class="cu-chip {{ $status === $value ? '!border-slate-900 !bg-slate-900 !text-white' : '' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Filtre actif</p>
                        <p class="mt-1 text-sm font-bold text-slate-800">{{ $activeFilterLabel }}</p>
                    </div>

                    <button wire:click="resetFilters" class="cu-btn-secondary">
                        Réinitialiser
                    </button>
                </div>
            </x-app-card>

            <x-app-card padding="p-6" :title="__('Abonnement')" :subtitle="__('Votre plan actuel et ses avantages.')">
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Plan</span>
                        <span class="font-semibold text-slate-800">
                            {{ ucfirst((string) $subscriptionSummary['plan_type']) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Statut</span>
                        <span class="font-semibold {{ $subscriptionSummary['is_past_due'] ? 'text-rose-700' : ($subscriptionSummary['is_premium'] ? 'text-emerald-700' : 'text-slate-700') }}">
                            {{ $subscriptionSummary['is_premium'] ? 'Actif' : ucfirst((string) $subscriptionSummary['plan_status']) }}
                        </span>
                    </div>

                    @if($subscriptionSummary['renewal_at'])
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Renouvellement</span>
                            <span class="font-semibold text-slate-800">
                                {{ optional($subscriptionSummary['renewal_at'])->format('d/m/Y') }}
                            </span>
                        </div>
                    @endif
                </div>

                <div class="mt-5 rounded-2xl border p-4 {{ $subscriptionSummary['is_premium'] ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50' }}">
                    <p class="text-sm font-black {{ $subscriptionSummary['is_premium'] ? 'text-amber-800' : 'text-slate-800' }}">
                        {{ $subscriptionSummary['is_premium'] ? 'Avantages Premium actifs' : 'Avantages Premium disponibles' }}
                    </p>

                    <ul class="mt-2 space-y-2 text-sm {{ $subscriptionSummary['is_premium'] ? 'text-amber-700' : 'text-slate-600' }}">
                        <li>• Choix des employés favoris</li>
                        <li>• Meilleure visibilité sur les disponibilités</li>
                        <li>• Gestion plus simple des réservations</li>
                    </ul>

                    @if(! $subscriptionSummary['is_premium'])
                        <a href="{{ route('premium.offer') }}" class="cu-btn-primary mt-4 !bg-amber-500 hover:!bg-amber-600">
                            Découvrir Premium
                        </a>
                    @endif
                </div>
            </x-app-card>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            @if($documentType !== 'invoices')
                <x-app-card padding="p-6" :title="__('Mes devis')" :subtitle="__('Derniers devis générés pour vos prestations.')">
                    <div class="space-y-4">
                        @forelse($quotes as $quote)
                            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-200 hover:shadow-md">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-lg font-black text-slate-900">
                                                {{ $quote->quote_number }}
                                            </p>

                                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $this->quoteStatusBadge((string) $quote->status) }}">
                                                {{ ucfirst((string) $quote->status) }}
                                            </span>
                                        </div>

                                        <p class="mt-2 text-sm font-semibold text-slate-700">
                                            {{ $quote->rendezVous?->service_display_name ?? 'Service non précisé' }}
                                        </p>

                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ $quote->rendezVous?->serviceZone?->name ?? 'Zone non précisée' }}
                                            @if($quote->rendezVous?->organizationSite)
                                                · {{ $quote->rendezVous->organizationSite->name }}
                                            @endif
                                        </p>

                                        <p class="mt-1 text-sm text-slate-500">
                                            Émis le {{ optional($quote->issued_at)->format('d/m/Y') ?: '—' }}
                                            · Valable jusqu’au {{ optional($quote->valid_until)->format('d/m/Y') ?: '—' }}
                                        </p>

                                        @if($quote->invoice)
                                            <p class="mt-2 text-xs font-semibold text-emerald-700">
                                                Facture liée : {{ $quote->invoice->invoice_number }}
                                            </p>
                                        @endif
                                    </div>

                                    <div class="flex flex-col items-start gap-3 lg:items-end">
                                        <p class="text-2xl font-black text-slate-900">
                                            {{ $quote->formatDocumentMoney($quote->total_amount) }}
                                        </p>

                                        <a href="{{ route('client.finance.quote.download', $quote) }}" class="cu-btn-secondary">
                                            📥 Télécharger
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <x-empty-state
                                :title="__('Aucun devis')"
                                :message="__('Vos devis apparaîtront ici dès qu’un rendez-vous sera chiffré.')"
                                icon="🧾"
                            />
                        @endforelse
                    </div>
                </x-app-card>
            @endif

            @if($documentType !== 'quotes')
                <x-app-card padding="p-6" :title="__('Mes factures')" :subtitle="__('Suivi des factures, échéances et montants restants.')">
                    <div class="space-y-4">
                        @forelse($invoices as $invoice)
                            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-200 hover:shadow-md
                                {{ $invoice->status === 'overdue' ? '!border-rose-200 bg-rose-50/40' : '' }}">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-lg font-black text-slate-900">
                                                {{ $invoice->invoice_number }}
                                            </p>

                                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $this->invoiceStatusBadge((string) $invoice->status) }}">
                                                {{ ucfirst((string) $invoice->status) }}
                                            </span>
                                        </div>

                                        <p class="mt-2 text-sm font-semibold text-slate-700">
                                            {{ $invoice->rendezVous?->service_display_name ?? 'Service non précisé' }}
                                        </p>

                                        <p class="mt-1 text-sm text-slate-500">
                                            Émise le {{ optional($invoice->issued_at)->format('d/m/Y') ?: '—' }}
                                            · Échéance {{ optional($invoice->due_at)->format('d/m/Y') ?: '—' }}
                                        </p>

                                        <p class="mt-1 text-sm text-slate-500">
                                            Reste à payer :
                                            <span class="font-bold {{ (float) $invoice->balance_due > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                                                {{ $invoice->formatDocumentMoney($invoice->balance_due) }}
                                            </span>
                                        </p>

                                        @if($invoice->status === 'overdue')
                                            <p class="mt-2 text-xs font-bold text-rose-700">
                                                ⚠️ Cette facture est en retard.
                                            </p>
                                        @endif
                                    </div>

                                    <div class="flex flex-col items-start gap-3 lg:items-end">
                                        <p class="text-2xl font-black text-slate-900">
                                            {{ $invoice->formatDocumentMoney($invoice->total_amount) }}
                                        </p>

                                        <a href="{{ route('client.finance.invoice.download', $invoice) }}" class="cu-btn-secondary">
                                            📥 Télécharger
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <x-empty-state
                                :title="__('Aucune facture')"
                                :message="__('Vos factures apparaîtront ici dès qu’une prestation sera confirmée ou terminée.')"
                                icon="📄"
                            />
                        @endforelse
                    </div>
                </x-app-card>
            @endif
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <x-app-card padding="p-6" :title="__('Paiements récents')" :subtitle="__('Dernières opérations enregistrées.')">
                <div class="space-y-3">
                    @forelse($latestPaymentEvents as $payment)
                        <div class="cu-list-item flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-800">
                                    {{ $payment->payment_reference ?: 'Paiement' }}
                                </p>

                                <p class="text-sm text-slate-500">
                                    {{ optional($payment->paid_at)->format('d/m/Y H:i') ?: optional($payment->created_at)->format('d/m/Y H:i') }}
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="font-black text-slate-900">
                                    {{ number_format((float) $payment->amount, 2, ',', ' ') }}
                                    {{ $financeSummary['currency_symbol'] ?? '€' }}
                                </p>

                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {{ $payment->status }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <x-empty-state
                            :title="__('Aucun paiement récent')"
                            :message="__('Vos paiements enregistrés apparaîtront ici.')"
                            icon="💳"
                        />
                    @endforelse
                </div>
            </x-app-card>

            <x-app-card padding="p-6" :title="__('Comprendre votre finance')" :subtitle="__('Lecture rapide des chiffres importants.')">
                <div class="space-y-3 text-sm text-slate-600">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="font-bold text-slate-900">Devis à valider</p>
                        <p class="mt-1">Ce sont les propositions de prix qui ne sont pas encore acceptées.</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="font-bold text-slate-900">Reste à payer</p>
                        <p class="mt-1">Montant encore ouvert sur vos factures non entièrement payées.</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="font-bold text-slate-900">Facture en retard</p>
                        <p class="mt-1">Facture dont la date d’échéance est dépassée ou marquée comme overdue.</p>
                    </div>
                </div>
            </x-app-card>

            <x-app-card padding="p-6" :title="__('Actions rapides')" :subtitle="__('Accès utiles depuis votre espace finance.')">
                <div class="grid grid-cols-1 gap-3">
                    <a href="{{ route('client.rendezvous.create') }}" class="cu-btn-primary">
                        ➕ Nouveau rendez-vous
                    </a>

                    <a href="{{ route('client.rendezvous.index') }}" class="cu-btn-secondary">
                        📅 Voir mes rendez-vous
                    </a>

                    <a href="{{ route('client.dashboard') }}" class="cu-btn-secondary">
                        ← Retour espace client
                    </a>

                    @if(! $subscriptionSummary['is_premium'])
                        <a href="{{ route('premium.offer') }}" class="cu-btn-primary !bg-amber-500 hover:!bg-amber-600">
                            ★ Passer Premium
                        </a>
                    @endif
                </div>
            </x-app-card>
        </section>
    </div>
</div>
