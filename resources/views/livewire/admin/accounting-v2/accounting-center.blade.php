<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Accounting v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Comptabilité & Exports</h1>
                <p class="text-sm text-slate-500">Ledger immuable double-entry + clôtures + exports CSV/FEC/Sage/QuickBooks</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Écritures</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['entries_total']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Périodes fermées</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['periods_closed']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Exports prêts</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['exports_ready']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Débit {{ $kpis['period_label'] }}</p>
                <p class="text-2xl font-black text-blue-600"><x-money :amount="(float) ($kpis['period_debit'] / 100)" /></p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Crédit {{ $kpis['period_label'] }}</p>
                <p class="text-2xl font-black text-amber-600"><x-money :amount="(float) ($kpis['period_credit'] / 100)" /></p>
            </div>
        </div>

        <div class="flex flex-nowrap gap-2 overflow-x-auto border-b border-slate-200">
            @foreach(['ledger' => 'Ledger', 'periods' => 'Périodes', 'exports' => 'Exports', 'fiscalite' => 'Fiscalité'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 min-h-[44px] inline-flex shrink-0 items-center whitespace-nowrap text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        @if($tab === 'ledger')
            <div class="flex flex-wrap gap-2">
                <input type="number" wire:model.live="filterYear" min="2020" max="2100" class="rounded-xl border-gray-300 text-sm w-28" placeholder="Année" />
                <select wire:model.live="filterMonth" class="rounded-xl border-gray-300 text-sm">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}">{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                    @endfor
                </select>
                <select wire:model.live="filterJournal" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous journaux</option>
                    <option value="VEN">VEN</option>
                    <option value="ACH">ACH</option>
                    <option value="BANK">BANK</option>
                    <option value="OD">OD</option>
                    <option value="INV">INV</option>
                </select>
                <input type="text" wire:model.live="filterAccount" placeholder="Compte (ex: 411)" class="rounded-xl border-gray-300 text-sm w-32" />
            </div>
        @elseif($tab === 'exports')
            <div class="rounded-2xl border bg-white p-4 shadow-sm space-y-3">
                <p class="text-sm font-bold text-slate-900">Générer un export</p>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                    <select wire:model="exportFormat" class="rounded-xl border-gray-300 text-sm">
                        <option value="csv">CSV générique</option>
                        <option value="fec">FEC (DGFiP FR)</option>
                        <option value="sage">Sage</option>
                        <option value="quickbooks_iif">QuickBooks IIF</option>
                    </select>
                    <input type="number" wire:model="exportYear" min="2020" max="2100" class="rounded-xl border-gray-300 text-sm" />
                    <select wire:model="exportMonth" class="rounded-xl border-gray-300 text-sm">
                        <option value="0">Année complète</option>
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                        @endfor
                    </select>
                    <button wire:click="generateExport" class="rounded-xl bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-700">
                        Générer
                    </button>
                </div>
            </div>
        @endif

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'ledger')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Journal</th>
                            <th class="px-4 py-2 text-left">Compte</th>
                            <th class="px-4 py-2 text-left">Libellé</th>
                            <th class="px-4 py-2 text-left">Débit</th>
                            <th class="px-4 py-2 text-left">Crédit</th>
                            <th class="px-4 py-2 text-left">Batch</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $e)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($e->posting_date)->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $e->journal_code }}</td>
                                <td class="px-4 py-2 text-xs"><span class="font-mono">{{ $e->account_code }}</span> <span class="text-slate-500">{{ $e->account_name }}</span></td>
                                <td class="px-4 py-2 text-xs">{{ \Illuminate\Support\Str::limit($e->label, 50) }}</td>
                                <td class="px-4 py-2 text-xs text-blue-600 font-mono">{{ $e->debit_cents ? number_format($e->debit_cents / 100, 2, ',', ' ') : '—' }}</td>
                                <td class="px-4 py-2 text-xs text-amber-600 font-mono">{{ $e->credit_cents ? number_format($e->credit_cents / 100, 2, ',', ' ') : '—' }}</td>
                                <td class="px-4 py-2 text-xs font-mono text-slate-400">{{ \Illuminate\Support\Str::limit($e->batch_id, 14) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucune écriture.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'periods')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Période</th>
                            <th class="px-4 py-2 text-left">Écritures</th>
                            <th class="px-4 py-2 text-left">Débit</th>
                            <th class="px-4 py-2 text-left">Crédit</th>
                            <th class="px-4 py-2 text-left">Équilibre</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $p)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $p->label() }}</td>
                                <td class="px-4 py-2 text-xs">{{ $p->entry_count }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ number_format($p->total_debit_cents / 100, 2, ',', ' ') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ number_format($p->total_credit_cents / 100, 2, ',', ' ') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $p->isBalanced() ? '✓' : '⚠️' }}</td>
                                <td class="px-4 py-2 text-xs">
                                    @if($p->is_closed)
                                        <span class="text-emerald-600">🔒 fermée</span>
                                    @else
                                        <span class="text-amber-600">○ ouverte</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right text-xs">
                                    @if(! $p->is_closed)
                                        <button wire:click="closePeriod({{ $p->period_year }}, {{ $p->period_month }})" class="text-indigo-600 hover:underline"
                                            onclick="return confirm('Clôturer {{ $p->label() }} ? Action figée.')">Clôturer</button>
                                    @else
                                        {{-- LE MOTIF EST DEMANDÉ AVANT L'APPEL, jamais après : rouvrir un exercice
                                             clos se justifie devant un contrôle, et le service le refuse sans raison
                                             écrite. `prompt` rend `null` si on annule — le composant refuse le vide. --}}
                                        <button type="button" class="text-amber-700 hover:underline"
                                            onclick="const m = prompt('Motif de réouverture de {{ $p->label() }} ?'); if (m) { @this.call('reopenPeriod', {{ $p->id }}, m); }">Rouvrir</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucune période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Format</th>
                            <th class="px-4 py-2 text-left">Période</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-left">Lignes</th>
                            <th class="px-4 py-2 text-left">Taille</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $ex)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($ex->created_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $ex->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $ex->format }}</td>
                                <td class="px-4 py-2 text-xs">{{ $ex->period_year }}{{ $ex->period_month ? '-' . str_pad((string) $ex->period_month, 2, '0', STR_PAD_LEFT) : '' }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $ex->status === 'ready',
                                        'bg-amber-100 text-amber-800' => $ex->status === 'pending',
                                        'bg-red-100 text-red-800' => $ex->status === 'failed',
                                        'bg-slate-100 text-slate-800' => $ex->status === 'expired',
                                    ])>{{ $ex->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs">{{ number_format($ex->row_count) }}</td>
                                <td class="px-4 py-2 text-xs">{{ $ex->file_size_bytes ? number_format($ex->file_size_bytes / 1024, 1) . ' KB' : '—' }}</td>
                                <td class="px-4 py-2 text-right text-xs">
                                    @if($ex->status === 'ready')
                                        <a href="/api/admin/accounting-v2/exports/{{ $ex->id }}/download" class="text-indigo-600 hover:underline">Télécharger</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Aucun export.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif

            @if($tab === 'fiscalite')
                <div class="p-6 space-y-8">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">Position fiscale &amp; postage</h2>
                        <p class="text-sm text-slate-500">
                            Ces réglages décident de ce qui entre au journal et sous quel régime de TVA.
                            Ils vivaient dans la configuration du serveur ; ils se changent désormais ici.
                        </p>
                    </div>

                    <label class="flex items-start gap-3">
                        <input type="checkbox" wire:model="postageAutomatique" class="mt-1 rounded border-gray-300" />
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">Écrire les écritures automatiquement</span>
                            <span class="block text-xs text-slate-500">
                                Coupé par défaut, exprès : tant qu'il l'est, aucun événement métier n'alimente le
                                grand livre. À lever une fois le plan comptable validé.
                            </span>
                        </span>
                    </label>

                    <div>
                        <label class="block text-sm font-semibold text-slate-900" for="tva-frais-annulation">
                            TVA sur les frais d'annulation
                        </label>
                        <p class="mb-2 text-xs text-slate-500">
                            Laisser <strong>vide</strong> applique le taux du pays de la réservation, comme pour un
                            produit ordinaire. Saisir <strong>0</strong> les traite comme une indemnité
                            <strong>hors champ</strong> de la TVA. Ce sont deux positions différentes, et le vide
                            n'est pas un zéro.
                        </p>
                        <input id="tva-frais-annulation" type="text" inputmode="decimal" wire:model="tvaFraisAnnulation"
                               placeholder="vide = taux du pays"
                               class="w-40 rounded-xl border-gray-300 text-sm" />
                        @error('tvaFraisAnnulation')
                            <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-900" for="modele-revenu">Modèle de revenu</label>
                        <p class="mb-2 text-xs text-slate-500">
                            <strong>Principal</strong> : le TTC complet est porté en ventes. <strong>Agent</strong> :
                            seule la commission est un produit, la part prestataire reste une dette jusqu'au
                            versement. Le choix change la base taxable — il vous revient.
                        </p>
                        <select id="modele-revenu" wire:model="modeleRevenu" class="rounded-xl border-gray-300 text-sm">
                            <option value="principal">Principal — TTC complet en ventes</option>
                            <option value="agent">Agent — commission seule en produit</option>
                        </select>
                        @error('modeleRevenu')
                            <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="button" wire:click="enregistrerLaFiscalite"
                            class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">
                        Enregistrer
                    </button>
                </div>
            @else
                <div class="p-3">{{ $items->links() }}</div>
            @endif
        </div>
    </div>
</div>
