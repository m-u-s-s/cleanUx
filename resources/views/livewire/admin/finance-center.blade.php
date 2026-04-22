<div class="space-y-6">
    <x-page-shell eyebrow="Finance" title="Finance center" subtitle="Devis, factures, suivi d’encaissement et marge estimée pilotés depuis les rendez-vous.">
        <x-slot name="actions">
            <button wire:click="syncFilteredDocuments" class="cu-btn-secondary">Sync filtres</button>
            <button wire:click="syncAllDocuments" class="cu-btn-primary">Sync globale</button>
            <button wire:click="exportFinanceCsv" class="cu-btn-secondary">Export CSV</button>
            @if($selectedRendezVous)
                <button wire:click="downloadQuotePdf({{ $selectedRendezVous->id }})" class="cu-btn-secondary">Devis PDF</button>
                <button wire:click="downloadInvoicePdf({{ $selectedRendezVous->id }})" class="cu-btn-secondary">Facture PDF</button>
            @endif
        </x-slot>
    </x-page-shell>

    @if (session()->has('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session()->has('warning'))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">{{ session('warning') }}</div>
    @endif

    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
        <x-kpi-card title="CA estimé HTVA" :value="'€ '.number_format($kpis['total_htva'], 2, ',', ' ')" tone="blue" icon="💼" />
        <x-kpi-card title="Entreprise HTVA" :value="'€ '.number_format($kpis['entreprise_htva'], 2, ',', ' ')" tone="amber" icon="🏢" />
        <x-kpi-card title="À facturer HTVA" :value="'€ '.number_format($kpis['to_invoice_htva'], 2, ',', ' ')" tone="slate" icon="🧾" />
        <x-kpi-card title="Marge estimée" :value="'€ '.number_format($kpis['margin_estimate'], 2, ',', ' ')" tone="green" icon="📈" />
        <x-kpi-card title="Solde à encaisser" :value="'€ '.number_format($kpis['outstanding_balance'], 2, ',', ' ')" tone="rose" icon="⏱️" />
        <x-kpi-card title="Factures en retard" :value="$kpis['overdue_count']" :hint="'€ '.number_format($kpis['overdue_balance'], 2, ',', ' ')" tone="red" icon="🚨" />
    </div>

    <div class="grid gap-4 lg:grid-cols-4">
        <x-filter-panel title="Filtres finance" subtitle="Recherche, période, marché, zone, service et état de paiement." class="lg:col-span-3">
            <div class="grid gap-3 md:grid-cols-4 xl:grid-cols-9">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Recherche" class="rounded-xl border-slate-300 text-sm shadow-sm xl:col-span-2">
                <input wire:model.live="dateFrom" type="date" class="rounded-xl border-slate-300 text-sm shadow-sm">
                <input wire:model.live="dateTo" type="date" class="rounded-xl border-slate-300 text-sm shadow-sm">
                <select wire:model.live="status" class="rounded-xl border-slate-300 text-sm shadow-sm">
                    <option value="">Tous statuts</option>
                    <option value="en_attente">En attente</option>
                    <option value="confirme">Confirmé</option>
                    <option value="en_route">En route</option>
                    <option value="sur_place">Sur place</option>
                    <option value="termine">Terminé</option>
                    <option value="annule">Annulé</option>
                    <option value="refuse">Refusé</option>
                </select>
                <select wire:model.live="market" class="rounded-xl border-slate-300 text-sm shadow-sm">
                    <option value="">Tous marchés</option>
                    <option value="particulier">Particulier</option>
                    <option value="entreprise">Entreprise</option>
                </select>
                <select wire:model.live="zoneId" class="rounded-xl border-slate-300 text-sm shadow-sm">
                    <option value="">Toutes zones</option>
                    @foreach($this->zones as $zone)
                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="serviceId" class="rounded-xl border-slate-300 text-sm shadow-sm">
                    <option value="">Tous services</option>
                    @foreach($this->services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="organizationId" class="rounded-xl border-slate-300 text-sm shadow-sm">
                    <option value="">Toutes entreprises</option>
                    @foreach($this->organizations as $organization)
                        <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="viewMode" class="rounded-xl border-slate-300 text-sm shadow-sm">
                    <option value="all">Tout</option>
                    <option value="quotes">Devis</option>
                    <option value="invoices">Factures</option>
                    <option value="cancelled">Annulations</option>
                </select>
                <select wire:model.live="paymentFilter" class="rounded-xl border-slate-300 text-sm shadow-sm xl:col-span-2">
                    <option value="">Tous paiements</option>
                    <option value="quoted_only">Devis sans facture</option>
                    <option value="pending">À encaisser</option>
                    <option value="paid">Payé</option>
                    <option value="overdue">En retard</option>
                </select>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-3 pr-4">Réf.</th>
                            <th class="py-3 pr-4">Date</th>
                            <th class="py-3 pr-4">Client</th>
                            <th class="py-3 pr-4">Service</th>
                            <th class="py-3 pr-4">Zone</th>
                            <th class="py-3 pr-4">Finance</th>
                            <th class="py-3 pr-4 text-right">HTVA</th>
                            <th class="py-3 pr-4 text-right">Solde</th>
                            <th class="py-3 pr-4 text-right">Marge</th>
                            <th class="py-3 pr-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rows as $row)
                            <tr class="{{ $selectedRendezVous && $selectedRendezVous->id === $row->id ? 'bg-slate-50' : 'bg-white' }}">
                                <td class="py-3 pr-4 font-medium text-slate-800">{{ $row->booking_reference ?: 'RDV-'.$row->id }}</td>
                                <td class="py-3 pr-4 text-slate-600">{{ optional($row->date)->format('d/m/Y') }}<br><span class="text-xs text-slate-400">{{ substr((string) $row->heure, 0, 5) }}</span></td>
                                <td class="py-3 pr-4 text-slate-600">
                                    <div>{{ $row->organizationAccount?->name ?: $row->client?->name }}</div>
                                    @if($row->organizationSite)
                                        <div class="text-xs text-slate-400">{{ $row->organizationSite->name }}</div>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-slate-600">{{ $row->service_display_name }}</td>
                                <td class="py-3 pr-4 text-slate-600">{{ $row->serviceZone?->name ?: '—' }}</td>
                                <td class="py-3 pr-4">
                                    <div class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 inline-block">{{ $this->financeStage($row) }}</div>
                                    @if($row->financeInvoice?->due_at)
                                        <div class="mt-1 text-xs text-slate-400">Échéance {{ $row->financeInvoice->due_at->format('d/m/Y') }}</div>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-right font-semibold text-slate-800">€ {{ number_format($this->amountHtva($row), 2, ',', ' ') }}</td>
                                <td class="py-3 pr-4 text-right text-slate-600">€ {{ number_format((float) ($row->financeInvoice?->balance_due ?? 0), 2, ',', ' ') }}</td>
                                <td class="py-3 pr-4 text-right text-slate-600">€ {{ number_format($this->marginEstimate($row), 2, ',', ' ') }}</td>
                                <td class="py-3 pr-4 text-right">
                                    <button wire:click="selectRendezVous({{ $row->id }})" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">Ouvrir</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center text-slate-400">Aucune donnée financière pour ces filtres.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $rows->links() }}
            </div>
        </x-filter-panel>

        <x-app-card title="Synthèse" subtitle="Vue rapide du rendez-vous sélectionné et des actions disponibles.">
            <h2 class="text-lg font-semibold text-slate-800">Document sélectionné</h2>

            @if($selectedRendezVous)
                <div class="mt-4 space-y-3 text-sm text-slate-600">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-400">Référence</div>
                        <div class="font-medium text-slate-800">{{ $selectedRendezVous->booking_reference ?: 'RDV-'.$selectedRendezVous->id }}</div>
                    </div>
                    <div class="grid gap-2">
                        <button wire:click="ensureQuoteDocument({{ $selectedRendezVous->id }})" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Sync devis</button>
                        <button wire:click="ensureInvoiceDocument({{ $selectedRendezVous->id }})" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Sync facture</button>
                        <button wire:click="issueInvoiceNow({{ $selectedRendezVous->id }})" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Émettre facture</button>
                        <button wire:click="sendInvoiceReminderNow({{ $selectedRendezVous->id }}, 'gentle')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Relance douce</button>
                        <button wire:click="sendInvoiceReminderNow({{ $selectedRendezVous->id }}, 'overdue')" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700">Relance retard</button>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-3">
                        <div class="text-xs uppercase tracking-wide text-slate-400">Montants</div>
                        <div class="mt-2 space-y-1">
                            <div>HTVA : <span class="font-semibold text-slate-800">€ {{ number_format($this->amountHtva($selectedRendezVous), 2, ',', ' ') }}</span></div>
                            <div>TVA : <span class="font-semibold text-slate-800">€ {{ number_format($this->amountTva($selectedRendezVous), 2, ',', ' ') }}</span></div>
                            <div>TVAC : <span class="font-semibold text-slate-800">€ {{ number_format($this->amountTvac($selectedRendezVous), 2, ',', ' ') }}</span></div>
                            <div>Marge estimée : <span class="font-semibold text-slate-800">€ {{ number_format($this->marginEstimate($selectedRendezVous), 2, ',', ' ') }}</span></div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-3">
                        <div class="text-xs uppercase tracking-wide text-slate-400">Documents</div>
                        <div class="mt-2 space-y-1">
                            <div>Devis : <span class="font-medium text-slate-800">{{ $selectedRendezVous->financeQuote?->quote_number ?: '—' }}</span></div>
                            <div>Facture : <span class="font-medium text-slate-800">{{ $selectedRendezVous->financeInvoice?->invoice_number ?: '—' }}</span></div>
                            <div>Statut facture : <span class="font-medium text-slate-800">{{ $selectedRendezVous->financeInvoice?->status ?: '—' }}</span></div>
                            <div>Solde : <span class="font-medium text-slate-800">€ {{ number_format((float) ($selectedRendezVous->financeInvoice?->balance_due ?? 0), 2, ',', ' ') }}</span></div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-3">
                        <div class="text-xs uppercase tracking-wide text-slate-400">Paiement manuel</div>
                        <div class="mt-2 grid gap-2">
                            <input wire:model.live="manualPaymentAmount" type="number" step="0.01" min="0" class="rounded-xl border-slate-300 text-sm shadow-sm" placeholder="Montant">
                            <select wire:model.live="manualPaymentMethod" class="rounded-xl border-slate-300 text-sm shadow-sm">
                                <option value="manual">Manuel</option>
                                <option value="bank_transfer">Virement</option>
                                <option value="cash">Cash</option>
                                <option value="card">Carte</option>
                            </select>
                            <button wire:click="recordPartialPaymentNow({{ $selectedRendezVous->id }})" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Enregistrer paiement partiel</button>
                            <button wire:click="markInvoicePaidNow({{ $selectedRendezVous->id }})" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Solder la facture</button>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-3">
                        <div class="text-xs uppercase tracking-wide text-slate-400">Contexte corporate</div>
                        <div class="mt-2 space-y-1">
                            <div>PO : {{ data_get($selectedRendezVous->pricing_snapshot, 'corporate_context.purchase_order_reference', '—') }}</div>
                            <div>Centre de coût : {{ data_get($selectedRendezVous->pricing_snapshot, 'corporate_context.cost_center', '—') }}</div>
                            <div>Échéance : {{ $selectedRendezVous->financeInvoice?->due_at?->format('d/m/Y') ?: '—' }}</div>
                            <div>Dernière relance : {{ optional($selectedRendezVous->financeInvoice?->reminders?->sortByDesc('created_at')->first()?->created_at)->format('d/m/Y H:i') ?: '—' }}</div>
                        </div>
                    </div>

                    <div class="grid gap-2 pt-2">
                        <button wire:click="downloadQuotePdf({{ $selectedRendezVous->id }})" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Télécharger devis</button>
                        <button wire:click="downloadInvoicePdf({{ $selectedRendezVous->id }})" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Télécharger facture</button>
                    </div>
                </div>
            @else
                <div class="mt-4 text-sm text-slate-400">Sélectionne un rendez-vous pour générer un devis, suivre l’encaissement et piloter la marge.</div>
            @endif
        </x-app-card>
    </div>
</div>
