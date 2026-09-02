{{-- L ECRAN DISAIT « aucun rendez-vous facturable » sans dire pourquoi, et cachait l instantane
     que le service ecrit deja : lignes datees, site, centre de cout, echeance, TVA, solde. --}}
<div class="space-y-6">
    <x-page-shell
        eyebrow="Finance B2B"
        title="Facturation mensuelle centralisée"
        subtitle="Regroupe les rendez-vous d’une société sur une période en une facture unique, ventilée par site et par centre de coût.">
        <x-slot:actions>
            <span class="brio-inline-stat">{{ $this->reperes['invoice_count'] }} facture(s)</span>
            @if($this->reperes['overdue_count'] > 0)
                <span class="brio-inline-stat">{{ $this->reperes['overdue_count'] }} en retard</span>
            @endif
        </x-slot:actions>
    </x-page-shell>

    {{-- LES REPERES SUIVENT LES FILTRES : ils decrivent le portefeuille affiche, pas la base. --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-kpi-card title="Encours à recouvrer" icon="⏳" :value="$this->reperes['outstanding_formatted']" />
        <x-kpi-card title="Encaissé" icon="✅" :value="$this->reperes['paid_formatted']" />
        <x-kpi-card title="En retard" icon="🔔" :value="$this->reperes['overdue_count']"
                    :hint="$this->reperes['overdue_balance'] > 0 ? 'Solde : '.$this->reperes['overdue_formatted'] : null" />
        <x-kpi-card title="Paiements partiels" icon="◐" :value="$this->reperes['partial_count']" />
    </div>

    {{-- ── Générateur, avec son aperçu ──────────────────────────────────── --}}
    <x-filter-panel title="Générer une facture mensuelle"
                    subtitle="Choisissez la société et la période : l’aperçu dit ce qui sera facturé avant que vous validiez.">
        <div class="brio-filter-grid">
            <div class="md:col-span-2">
                <label for="organization_account_id" class="mb-1 block text-sm font-semibold">Société</label>
                <select id="organization_account_id" wire:model.live="organization_account_id" class="w-full">
                    <option value="">— Choisir —</option>
                    @foreach($organizations as $organization)
                        <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                    @endforeach
                </select>
                @error('organization_account_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="period_start" class="mb-1 block text-sm font-semibold">Début de période</label>
                <input id="period_start" type="date" wire:model.live="period_start" class="w-full">
                @error('period_start') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="period_end" class="mb-1 block text-sm font-semibold">Fin de période</label>
                <input id="period_end" type="date" wire:model.live="period_end" class="w-full">
                @error('period_end') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        @if($this->apercu['pret'] ?? false)
            @php($apercu = $this->apercu)

            <div class="mt-4 rounded-2xl border border-slate-200/80 p-4 dark:border-slate-700">
                @if($apercu['eligibles'] > 0)
                    <p class="text-sm font-semibold">
                        {{ $apercu['eligibles'] }} rendez-vous facturable(s) —
                        <x-money :amount="(float) $apercu['montant']" /> HTVA
                    </p>

                    <div class="mt-3 grid gap-2 md:grid-cols-2">
                        @foreach($apercu['par_site'] as $site)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span>{{ $site['site'] }}</span>
                                <span class="opacity-70">
                                    {{ $site['count'] }} RDV · <x-money :amount="(float) $site['subtotal']" />
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- LA RAISON, PAS SEULEMENT LE CONSTAT. Sans elle, l administrateur clique,
                         lit « aucun rendez-vous facturable » et n a aucun moyen d avancer. --}}
                    <p class="text-sm font-semibold">Rien à facturer sur cette période.</p>

                    <ul class="mt-2 space-y-1 text-sm opacity-80">
                        <li>{{ $apercu['dans_la_periode'] }} rendez-vous dans la période.</li>

                        @if($apercu['deja_facturees'] > 0)
                            <li>{{ $apercu['deja_facturees'] }} déjà rattaché(s) à une facture.</li>
                        @endif

                        @forelse($apercu['statuts_ecartes'] as $statut => $nombre)
                            <li>{{ $nombre }} au statut « {{ $statut }} » — seuls « terminé » et « confirmé » sont facturables.</li>
                        @empty
                            @if($apercu['dans_la_periode'] === 0)
                                <li>Aucun rendez-vous n’est rattaché à cette société sur cette période.</li>
                            @endif
                        @endforelse
                    </ul>
                @endif
            </div>
        @endif

        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" wire:click="generate" class="brio-btn-primary">
                Générer la facture groupée
            </button>

            <button type="button" wire:click="genererPourToutesLesSocietes"
                    wire:confirm="Générer la facture mensuelle de toutes les sociétés éligibles sur cette période ?"
                    class="brio-btn-ligne">
                Clôturer le mois pour toutes les sociétés
            </button>
        </div>
    </x-filter-panel>

    {{-- ── Filtres de la liste ──────────────────────────────────────────── --}}
    <x-filter-panel title="Factures générées" subtitle="Isole une société, un statut ou un numéro de facture.">
        <div class="brio-filter-grid">
            <input wire:model.live.debounce.300ms="recherche" type="text"
                   placeholder="Numéro de facture, société…" aria-label="Recherche">

            <select wire:model.live="filtreOrganisation" aria-label="Société">
                <option value="">Toutes les sociétés</option>
                @foreach($organizations as $organization)
                    <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="filtreStatut" aria-label="Statut">
                <option value="">Tous statuts</option>
                <option value="issued">Émise</option>
                <option value="partial">Partiellement payée</option>
                <option value="paid">Payée</option>
                <option value="retard">En retard</option>
            </select>

            <button type="button" wire:click="reinitialiserLesFiltres" class="brio-btn-ligne">Réinitialiser</button>
        </div>
    </x-filter-panel>

    <x-table-shell>
        <table class="min-w-full text-sm">
            <thead>
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Facture</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Société</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Période</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold">Total TTC</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold">Solde</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Échéance</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Statut</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold">Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse($invoices as $invoice)
                    @php($enRetard = (float) $invoice->balance_due > 0 && $invoice->due_at && now()->gt($invoice->due_at))

                    <tr wire:key="facture-{{ $invoice->id }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold">{{ $invoice->invoice_number }}</p>
                            <p class="text-xs opacity-70">
                                {{ count((array) $invoice->site_breakdown) }} site(s) ·
                                {{ data_get($invoice->meta, 'rendez_vous_count', 0) }} RDV
                            </p>
                        </td>

                        <td class="px-4 py-3">{{ $invoice->organizationAccount?->name ?? '—' }}</td>

                        <td class="px-4 py-3">
                            {{ $invoice->billing_period_start?->format('d/m/Y') }}
                            <span class="opacity-50">→</span>
                            {{ $invoice->billing_period_end?->format('d/m/Y') }}
                        </td>

                        <td class="px-4 py-3 text-right font-semibold tabular-nums">
                            <x-money :amount="(float) $invoice->total_amount" :currency="$invoice->currency" />
                            <span class="block text-xs font-normal opacity-70">
                                dont TVA {{ number_format((float) $invoice->tax_rate, 0) }} %
                            </span>
                        </td>

                        <td class="px-4 py-3 text-right tabular-nums">
                            <x-money :amount="(float) $invoice->balance_due" :currency="$invoice->currency" />
                        </td>

                        <td class="px-4 py-3">
                            {{ $invoice->due_at?->format('d/m/Y') ?? '—' }}
                            @if($enRetard)
                                <span class="block text-xs font-semibold text-red-600">
                                    {{ $invoice->due_at->diffInDays(now()) }} j de retard
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3"><x-badge :status="$invoice->status" /></td>

                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="ouvrirLaFacture({{ $invoice->id }})"
                                        class="brio-btn-ligne">Détail</button>

                                @if((float) $invoice->balance_due > 0)
                                    <button type="button" wire:click="ouvrirLePaiement({{ $invoice->id }})"
                                            class="brio-btn-ligne-accent">Paiement</button>

                                    <button type="button" wire:click="envoyerUneRelance({{ $invoice->id }})"
                                            class="brio-btn-ligne">Relancer</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <x-empty-state
                                icon="🧾"
                                title="Aucune facture B2B"
                                message="Aucune facture ne correspond à ces filtres. Choisissez une société ci-dessus : l’aperçu vous dira ce qui est facturable." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-4 py-3">{{ $invoices->links() }}</div>
    </x-table-shell>

    {{-- ── Détail : l instantané que le service écrivait sans jamais le montrer ── --}}
    @if($this->factureDetaillee)
        @php($facture = $this->factureDetaillee)

        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4"
             role="dialog" aria-modal="true" aria-labelledby="titre-facture">
            <div class="brio-card my-8 w-full max-w-4xl p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="titre-facture" class="brio-section-title">{{ $facture->invoice_number }}</h2>
                        <p class="brio-section-subtitle">
                            {{ $facture->organizationAccount?->name }} ·
                            {{ $facture->billing_period_start?->format('d/m/Y') }}
                            → {{ $facture->billing_period_end?->format('d/m/Y') }}
                        </p>
                    </div>

                    <button type="button" wire:click="fermerLaFacture" class="brio-btn-ligne" aria-label="Fermer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7"
                             stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div>
                        <p class="text-xs opacity-70">Sous-total</p>
                        <p class="font-semibold tabular-nums"><x-money :amount="(float) $facture->subtotal" :currency="$facture->currency" /></p>
                    </div>
                    <div>
                        <p class="text-xs opacity-70">TVA {{ number_format((float) $facture->tax_rate, 0) }} %</p>
                        <p class="font-semibold tabular-nums"><x-money :amount="(float) $facture->tax_amount" :currency="$facture->currency" /></p>
                    </div>
                    <div>
                        <p class="text-xs opacity-70">Total TTC</p>
                        <p class="font-semibold tabular-nums"><x-money :amount="(float) $facture->total_amount" :currency="$facture->currency" /></p>
                    </div>
                    <div>
                        <p class="text-xs opacity-70">Solde</p>
                        <p class="font-semibold tabular-nums"><x-money :amount="(float) $facture->balance_due" :currency="$facture->currency" /></p>
                    </div>
                </div>

                <h3 class="mt-6 text-sm font-bold">Ventilation par site</h3>
                <div class="brio-table-cadre mt-2">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="px-3 py-2 text-left font-semibold">Site</th>
                                <th scope="col" class="px-3 py-2 text-right font-semibold">RDV</th>
                                <th scope="col" class="px-3 py-2 text-right font-semibold">Sous-total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach((array) $facture->site_breakdown as $site)
                                <tr>
                                    <td class="px-3 py-2">{{ $site['site'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $site['count'] ?? 0 }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        <x-money :amount="(float) ($site['subtotal'] ?? 0)" :currency="$facture->currency" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- LE CENTRE DE COUT ETAIT PROMIS PAR LE SOUS-TITRE ET N APPARAISSAIT NULLE PART.
                     Le service l ecrit sur chaque ligne de l instantane depuis toujours. --}}
                <h3 class="mt-6 text-sm font-bold">Lignes détaillées</h3>
                <div class="brio-table-cadre mt-2 max-h-80 overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="px-3 py-2 text-left font-semibold">Date</th>
                                <th scope="col" class="px-3 py-2 text-left font-semibold">Référence</th>
                                <th scope="col" class="px-3 py-2 text-left font-semibold">Site</th>
                                <th scope="col" class="px-3 py-2 text-left font-semibold">Centre de coût</th>
                                <th scope="col" class="px-3 py-2 text-left font-semibold">Service</th>
                                <th scope="col" class="px-3 py-2 text-right font-semibold">Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse((array) data_get($facture->snapshot, 'lines', []) as $ligne)
                                <tr>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        {{ $ligne['date'] ?? '—' }}
                                        <span class="opacity-60">{{ $ligne['heure'] ?? '' }}</span>
                                    </td>
                                    <td class="px-3 py-2">{{ $ligne['booking_reference'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $ligne['site'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $ligne['cost_center'] ?: '—' }}</td>
                                    <td class="px-3 py-2">{{ $ligne['service'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        <x-money :amount="(float) ($ligne['amount'] ?? 0)" :currency="$facture->currency" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-4 text-center opacity-70">
                                        Cette facture ne porte aucune ligne détaillée.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($facture->payments->isNotEmpty() || $facture->reminders->isNotEmpty())
                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <div>
                            <h3 class="text-sm font-bold">Paiements</h3>
                            @forelse($facture->payments as $paiement)
                                <p class="mt-1 text-sm">
                                    {{ $paiement->paid_at?->format('d/m/Y') }} ·
                                    <x-money :amount="(float) $paiement->amount" :currency="$facture->currency" />
                                    <span class="opacity-70">({{ $paiement->method }})</span>
                                </p>
                            @empty
                                <p class="mt-1 text-sm opacity-70">Aucun paiement enregistré.</p>
                            @endforelse
                        </div>

                        <div>
                            <h3 class="text-sm font-bold">Relances</h3>
                            @forelse($facture->reminders as $relance)
                                <p class="mt-1 text-sm">
                                    {{ $relance->sent_at?->format('d/m/Y') ?? '—' }} ·
                                    {{ $relance->reminder_type }}
                                    <span class="opacity-70">({{ $relance->status }})</span>
                                </p>
                            @empty
                                <p class="mt-1 text-sm opacity-70">Aucune relance envoyée.</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ── Enregistrer un paiement ──────────────────────────────────────── --}}
    @if($facturePourPaiement)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
             role="dialog" aria-modal="true" aria-labelledby="titre-paiement">
            <div class="brio-card w-full max-w-md p-6">
                <h2 id="titre-paiement" class="brio-section-title">Enregistrer un paiement</h2>
                <p class="brio-section-subtitle mt-1">
                    Le solde et le statut de la facture se recalculent tout seuls après l’enregistrement.
                </p>

                <form wire:submit="enregistrerLePaiement" class="mt-4 space-y-3">
                    <div>
                        <label for="paiement-montant" class="mb-1 block text-sm font-semibold">Montant</label>
                        <input id="paiement-montant" wire:model="montantDuPaiement" type="number" step="0.01" min="0.01" class="w-full">
                        @error('montantDuPaiement') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="paiement-methode" class="mb-1 block text-sm font-semibold">Moyen</label>
                        <select id="paiement-methode" wire:model="methodeDePaiement" class="w-full">
                            <option value="transfer">Virement</option>
                            <option value="card">Carte</option>
                            <option value="cash">Espèces</option>
                            <option value="manual">Autre</option>
                        </select>
                        @error('methodeDePaiement') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="paiement-reference" class="mb-1 block text-sm font-semibold">Référence externe</label>
                        <input id="paiement-reference" wire:model="referenceDePaiement" type="text" class="w-full"
                               placeholder="Communication structurée, n° de virement…">
                        @error('referenceDePaiement') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="fermerLePaiement" class="brio-btn-ligne">Annuler</button>
                        <button type="submit" class="brio-btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
