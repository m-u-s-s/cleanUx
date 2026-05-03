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
                    <td class="py-3 pr-4 text-slate-600">
                        {{ optional($row->date)->format('d/m/Y') }}<br>
                        <span class="text-xs text-slate-400">{{ substr((string) $row->heure, 0, 5) }}</span>
                    </td>
                    <td class="py-3 pr-4 text-slate-600">
                        <div>{{ $row->organizationAccount?->name ?: $row->client?->name }}</div>
                        @if($row->organizationSite)
                            <div class="text-xs text-slate-400">{{ $row->organizationSite->name }}</div>
                        @endif
                    </td>
                    <td class="py-3 pr-4 text-slate-600">{{ $row->service_display_name }}</td>
                    <td class="py-3 pr-4 text-slate-600">{{ $row->serviceZone?->name ?: '—' }}</td>
                    <td class="py-3 pr-4">
                        <div class="inline-block rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $this->financeStage($row) }}</div>
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
