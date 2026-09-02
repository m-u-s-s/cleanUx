{{-- L ECRAN ANNONCAIT UN SUCCES POUR UN GESTE SANS EFFET, et partageait UNE note entre toutes
     les cartes. Il taisait aussi le bon de commande, le centre de cout et le parcours date. --}}
<div class="space-y-6">
    <x-page-shell
        eyebrow="Comptes entreprises"
        title="Approbations entreprises"
        subtitle="La double validation B2B : le manager engage, la finance confirme. Un rendez-vous n’est confirmé qu’après les deux.">
        <x-slot:actions>
            <span class="brio-inline-stat">
                {{ $this->reperes['attente_manager'] + $this->reperes['attente_finance'] }} en attente
            </span>
            @if($this->reperes['montant_en_attente'] > 0)
                <span class="brio-inline-stat">
                    <x-money :amount="(float) $this->reperes['montant_en_attente']" /> engagés
                </span>
            @endif
        </x-slot:actions>
    </x-page-shell>

    {{-- LES REPERES SUIVENT LES FILTRES : ils decrivent la file affichee, pas la base. --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-kpi-card title="Attente manager" icon="🕐" :value="$this->reperes['attente_manager']" />
        <x-kpi-card title="Attente finance" icon="💶" :value="$this->reperes['attente_finance']" />
        <x-kpi-card title="Approuvées" icon="✅" :value="$this->reperes['approuvees']" />
        <x-kpi-card title="Refusées" icon="⛔" :value="$this->reperes['refusees']" />
    </div>

    <x-filter-panel title="Filtres" subtitle="Isole une société, un statut, un bon de commande ou un centre de coût.">
        <div class="brio-filter-grid">
            <input wire:model.live.debounce.300ms="search" type="text"
                   placeholder="Client, société, site, bon de commande…" aria-label="Recherche">

            <select wire:model.live="organisation" aria-label="Société">
                <option value="">Toutes les sociétés</option>
                @foreach($this->organisations as $societe)
                    <option value="{{ $societe->id }}">{{ $societe->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="status" aria-label="Statut">
                <option value="">Tous statuts</option>
                <option value="pending_manager">En attente manager</option>
                <option value="pending_finance">En attente finance</option>
                <option value="approved">Approuvée</option>
                <option value="rejected">Refusée</option>
                <option value="cancelled">Annulée</option>
            </select>

            <button type="button" wire:click="reinitialiserLesFiltres" class="brio-btn-ligne">Réinitialiser</button>
        </div>
    </x-filter-panel>

    <div class="space-y-4">
        @forelse($approvals as $approval)
            @php($rdv = $approval->rendezVous)
            @php($enAttente = in_array($approval->status, ['pending_manager', 'pending_finance'], true))

            <div class="brio-card p-5" wire:key="demande-{{ $approval->id }}">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0">
                        <h3 class="font-bold">{{ $approval->organizationAccount?->name ?? 'Entreprise inconnue' }}</h3>

                        <p class="text-sm opacity-70">
                            {{ collect([
                                $approval->organizationSite?->name,
                                $rdv?->client?->name,
                                $rdv?->service_display_name,
                            ])->filter()->join(' · ') ?: '—' }}
                        </p>

                        {{-- LE BON DE COMMANDE ET LE CENTRE DE COUT vivent dans cette table depuis
                             toujours, et aucun ecran ne les montrait. --}}
                        @if($approval->purchase_order_number || $approval->cost_center)
                            <p class="mt-1 text-xs opacity-70">
                                @if($approval->purchase_order_number)
                                    Bon de commande : <span class="font-semibold">{{ $approval->purchase_order_number }}</span>
                                @endif
                                @if($approval->cost_center)
                                    @if($approval->purchase_order_number) · @endif
                                    Centre de coût : <span class="font-semibold">{{ $approval->cost_center }}</span>
                                @endif
                            </p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <x-badge :status="$approval->status" />
                        <button type="button" wire:click="ouvrirLaDemande({{ $approval->id }})"
                                class="brio-btn-ligne">Détail</button>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                    <div>
                        <p class="text-xs opacity-70">Date du rendez-vous</p>
                        <p class="font-semibold">
                            {{ $rdv?->date?->format('d/m/Y') ?? '—' }}
                            @if($rdv?->heure)
                                <span class="opacity-70">à {{ substr((string) $rdv->heure, 0, 5) }}</span>
                            @endif
                        </p>
                    </div>

                    <div>
                        <p class="text-xs opacity-70">Devis estimé</p>
                        <p class="font-semibold tabular-nums">
                            <x-money :amount="(float) ($rdv?->devis_estime ?? 0)" />
                        </p>
                    </div>

                    <div>
                        <p class="text-xs opacity-70">Manager</p>
                        <p class="font-semibold">
                            {{ $approval->managerApprovedBy?->name ?? '—' }}
                            @if($approval->manager_approved_at)
                                <span class="block text-xs font-normal opacity-70">
                                    {{ $approval->manager_approved_at->format('d/m/Y') }}
                                </span>
                            @endif
                        </p>
                    </div>

                    <div>
                        <p class="text-xs opacity-70">Finance</p>
                        <p class="font-semibold">
                            {{ $approval->financeApprovedBy?->name ?? '—' }}
                            @if($approval->finance_approved_at)
                                <span class="block text-xs font-normal opacity-70">
                                    {{ $approval->finance_approved_at->format('d/m/Y') }}
                                </span>
                            @endif
                        </p>
                    </div>
                </div>

                @if($approval->request_note || $approval->manager_note || $approval->finance_note || $approval->rejection_reason)
                    <div class="mt-4 space-y-1 rounded-2xl border border-slate-200/80 p-4 text-sm dark:border-slate-700">
                        @if($approval->request_note)
                            <p><span class="font-semibold">Note de la demande :</span> {{ $approval->request_note }}</p>
                        @endif
                        @if($approval->manager_note)
                            <p><span class="font-semibold">Note manager :</span> {{ $approval->manager_note }}</p>
                        @endif
                        @if($approval->finance_note)
                            <p><span class="font-semibold">Note finance :</span> {{ $approval->finance_note }}</p>
                        @endif
                        @if($approval->rejection_reason)
                            <p class="text-red-600"><span class="font-semibold">Motif du refus :</span> {{ $approval->rejection_reason }}</p>
                        @endif
                    </div>
                @endif

                @if($enAttente)
                    <div class="mt-4 space-y-3">
                        {{-- UNE NOTE PAR DEMANDE. Une propriete unique partagee par toutes les
                             cartes attachait la note saisie ici au clic donne ailleurs. --}}
                        <label class="sr-only" for="note-{{ $approval->id }}">Note interne pour cette demande</label>
                        <textarea id="note-{{ $approval->id }}" wire:model="notes.{{ $approval->id }}" rows="2"
                                  class="w-full" placeholder="Note interne facultative, attachée à cette demande…"></textarea>

                        <div class="flex flex-wrap gap-2">
                            @if($approval->status === 'pending_manager')
                                <button type="button" wire:click="approveManager({{ $approval->id }})"
                                        class="brio-btn-primary">Valider — manager</button>
                            @endif

                            @if($approval->status === 'pending_finance')
                                <button type="button" wire:click="approveFinance({{ $approval->id }})"
                                        class="brio-btn-accent">Valider — finance</button>
                            @endif

                            <button type="button" wire:click="openRejectModal({{ $approval->id }})"
                                    class="brio-btn-ligne-danger">Refuser</button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <x-empty-state
                icon="📑"
                title="Aucune demande d’approbation"
                message="Aucune demande entreprise ne correspond à ces filtres. Une demande naît quand une société réserve sous un contrat exigeant une validation." />
        @endforelse
    </div>

    <div>{{ $approvals->links() }}</div>

    {{-- ── Détail : le parcours daté de la demande ──────────────────────── --}}
    @if($this->demandeDetaillee)
        @php($demande = $this->demandeDetaillee)

        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4"
             role="dialog" aria-modal="true" aria-labelledby="titre-demande">
            <div class="brio-card my-8 w-full max-w-2xl p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="titre-demande" class="brio-section-title">
                            {{ $demande->organizationAccount?->name ?? 'Entreprise inconnue' }}
                        </h2>
                        <p class="brio-section-subtitle">
                            {{ $demande->organizationSite?->name ?? 'Sans site' }} ·
                            {{ $demande->rendezVous?->service_display_name ?? '—' }}
                        </p>
                    </div>

                    <button type="button" wire:click="fermerLaDemande" class="brio-btn-ligne" aria-label="Fermer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7"
                             stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- LES TROIS HORODATAGES existaient en base et n apparaissaient nulle part :
                     impossible de savoir combien de temps une demande avait attendu. --}}
                <h3 class="mt-5 text-sm font-bold">Parcours de la demande</h3>
                <ol class="mt-2 space-y-2 text-sm">
                    <li class="flex items-start justify-between gap-3">
                        <span>Demandée{{ $demande->requestedBy ? ' par '.$demande->requestedBy->name : '' }}</span>
                        <span class="shrink-0 opacity-70">{{ $demande->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
                    </li>
                    <li class="flex items-start justify-between gap-3">
                        <span>Validée manager{{ $demande->managerApprovedBy ? ' par '.$demande->managerApprovedBy->name : '' }}</span>
                        <span class="shrink-0 opacity-70">{{ $demande->manager_approved_at?->format('d/m/Y H:i') ?? 'en attente' }}</span>
                    </li>
                    <li class="flex items-start justify-between gap-3">
                        <span>Validée finance{{ $demande->financeApprovedBy ? ' par '.$demande->financeApprovedBy->name : '' }}</span>
                        <span class="shrink-0 opacity-70">{{ $demande->finance_approved_at?->format('d/m/Y H:i') ?? 'en attente' }}</span>
                    </li>
                    @if($demande->rejected_at)
                        <li class="flex items-start justify-between gap-3 text-red-600">
                            <span>Refusée</span>
                            <span class="shrink-0">{{ $demande->rejected_at->format('d/m/Y H:i') }}</span>
                        </li>
                    @endif
                </ol>

                <h3 class="mt-6 text-sm font-bold">Rendez-vous concerné</h3>
                <dl class="mt-2 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs opacity-70">Client</dt>
                        <dd class="font-semibold">{{ $demande->rendezVous?->client?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs opacity-70">Date</dt>
                        <dd class="font-semibold">{{ $demande->rendezVous?->date?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs opacity-70">Devis estimé</dt>
                        <dd class="font-semibold tabular-nums">
                            <x-money :amount="(float) ($demande->rendezVous?->devis_estime ?? 0)" />
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs opacity-70">Statut du rendez-vous</dt>
                        <dd><x-badge :status="$demande->rendezVous?->status ?? 'inconnu'" /></dd>
                    </div>
                    @if($demande->purchase_order_number)
                        <div>
                            <dt class="text-xs opacity-70">Bon de commande</dt>
                            <dd class="font-semibold">{{ $demande->purchase_order_number }}</dd>
                        </div>
                    @endif
                    @if($demande->cost_center)
                        <div>
                            <dt class="text-xs opacity-70">Centre de coût</dt>
                            <dd class="font-semibold">{{ $demande->cost_center }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    @endif

    {{-- ── Refus ────────────────────────────────────────────────────────── --}}
    @if($selectedApprovalId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
             role="dialog" aria-modal="true" aria-labelledby="titre-refus">
            <div class="brio-card w-full max-w-md p-6">
                <h2 id="titre-refus" class="brio-section-title">Refuser cette demande</h2>
                <p class="brio-section-subtitle mt-1">
                    Le rendez-vous passera en refusé. Le motif est conservé et reste lisible par la société.
                </p>

                <form wire:submit="reject" class="mt-4 space-y-3">
                    <div>
                        <label for="motif-refus" class="mb-1 block text-sm font-semibold">Motif du refus</label>
                        <textarea id="motif-refus" wire:model="rejectionReason" rows="3" class="w-full"
                                  placeholder="Budget dépassé, site non couvert, doublon…"></textarea>
                        @error('rejectionReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="closeRejectModal" class="brio-btn-ligne">Annuler</button>
                        <button type="submit" class="brio-btn-danger">Refuser la demande</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
