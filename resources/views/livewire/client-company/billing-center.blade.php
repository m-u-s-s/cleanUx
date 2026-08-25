<div>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900">🧾 Facturation</h1>
            <p class="text-sm text-slate-500">Toutes vos factures consolidées en un seul endroit</p>
        </div>
        <button onclick="window.print()"
            class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            📥 Exporter
        </button>
    </div>

    {{-- Une SOCIETE marocaine facture en dirhams : le symbole suit sa position. --}}
    @php($deviseSociete = app(\App\Services\Localization\Money::class)->symbol(\App\View\Components\Money::deviseDuContexte()))

    {{-- Résumé financier --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            ['label' => 'Ce mois',        'value' => number_format($summary['total_month'], 2) . ' ' . $deviseSociete, 'color' => 'text-slate-900'],
            ['label' => 'Cette année',    'value' => number_format($summary['total_year'], 2) . ' ' . $deviseSociete,  'color' => 'text-blue-700'],
            ['label' => 'Impayées',       'value' => number_format($summary['unpaid'], 2) . ' ' . $deviseSociete,       'color' => 'text-red-600'],
            ['label' => 'Factures/mois',  'value' => $summary['count_month'],                           'color' => 'text-slate-700'],
        ] as $s)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xl font-black {{ $s['color'] }}">{{ $s['value'] }}</p>
                <p class="text-xs text-slate-500 mt-0.5">{{ $s['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Filtres --}}
    <div class="mb-4 flex flex-wrap gap-3">
        <input wire:model.live.debounce.300ms="search" type="text"
            placeholder="Rechercher une facture…"
            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm w-48 outline-none focus:border-sky-500">

        <select wire:model.live="filterStatus"
            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous statuts</option>
            <option value="paid">✅ Payée</option>
            <option value="pending">⏳ En attente</option>
            <option value="overdue">🔴 En retard</option>
        </select>

        <select wire:model.live="filterPeriod"
            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="all">Toutes périodes</option>
            <option value="month">Ce mois</option>
            <option value="quarter">Ce trimestre</option>
            <option value="year">Cette année</option>
        </select>

        <select wire:model.live="filterSite"
            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les locaux</option>
            @foreach ($sites as $site)
                <option value="{{ $site->id }}">{{ $site->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Tableau factures --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-100 text-[10px] uppercase tracking-wider text-slate-400">
                    <th class="px-5 py-3 text-left">Référence</th>
                    <th class="px-5 py-3 text-left hidden sm:table-cell">Local</th>
                    <th class="px-5 py-3 text-left hidden md:table-cell">Date</th>
                    <th class="px-5 py-3 text-right">Montant</th>
                    <th class="px-5 py-3 text-center hidden sm:table-cell">Statut</th>
                    <th class="px-5 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($invoices as $invoice)
                    <tr class="transition hover:bg-slate-50">
                        {{-- `invoice_number` est le champ réel : `reference` n'a jamais existé sur ce modèle. --}}
                        <td class="px-5 py-4 text-sm font-semibold text-slate-900">{{ $invoice->invoice_number ?: '#'.$invoice->id }}</td>
                        {{-- Le site vient de la réservation facturée ; le modèle n'a pas de relation `site` directe. --}}
                        <td class="px-5 py-4 text-sm text-slate-600 hidden sm:table-cell">{{ $invoice->rendezVous?->organizationSite?->name ?? '—' }}</td>
                        <td class="px-5 py-4 text-sm text-slate-500 hidden md:table-cell">
                            {{ $invoice->issued_at?->format('d/m/Y') ?? $invoice->created_at?->format('d/m/Y') }}
                        </td>
                        <td class="px-5 py-4 text-right text-sm font-semibold text-slate-900">
                            <x-money :amount="(float) ($invoice->total_amount ?? 0)" />
                        </td>
                        <td class="px-5 py-4 text-center hidden sm:table-cell">
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-bold
                                {{ match($invoice->status ?? 'pending') {
                                    'paid'    => 'bg-green-100 text-green-700',
                                    'overdue' => 'bg-red-100 text-red-700',
                                    default   => 'bg-amber-100 text-amber-700',
                                } }}">
                                {{ match($invoice->status ?? 'pending') {
                                    'paid'    => 'Payée',
                                    'overdue' => 'En retard',
                                    default   => 'En attente',
                                } }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <button wire:click="downloadInvoice({{ $invoice->id }})"
                                class="rounded-lg px-3 py-1.5 text-xs font-medium text-sky-600 border border-sky-200 hover:bg-sky-50 transition">
                                📥 PDF
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-16 text-center">
                            <p class="text-3xl mb-2">🧾</p>
                            <p class="text-sm text-slate-400">Aucune facture pour le moment</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($invoices instanceof \Illuminate\Contracts\Pagination\Paginator)
            <div class="border-t border-slate-100 px-5 py-3">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
