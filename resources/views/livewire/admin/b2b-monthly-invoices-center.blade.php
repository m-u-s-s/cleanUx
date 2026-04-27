<x-page-shell
    title="💼 Facturation B2B centralisée"
    subtitle="Générez des factures mensuelles groupées par entreprise, site et centre de coût.">

    <div class="rounded-2xl border bg-white p-5 shadow-sm space-y-4">
        <h3 class="font-semibold text-slate-900">Générer une facture mensuelle</h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="text-sm font-medium text-slate-700">Entreprise</label>
                <select wire:model="organization_account_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="">— Choisir —</option>
                    @foreach($organizations as $organization)
                        <option value="{{ $organization->id }}">
                            {{ $organization->name }}
                        </option>
                    @endforeach
                </select>
                @error('organization_account_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Début période</label>
                <input type="date" wire:model="period_start" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                @error('period_start') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Fin période</label>
                <input type="date" wire:model="period_end" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                @error('period_end') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <button
            type="button"
            wire:click="generate"
            class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            Générer la facture groupée
        </button>
    </div>

    <div class="rounded-2xl border bg-white shadow-sm overflow-hidden">
        <div class="border-b p-5">
            <h3 class="font-semibold text-slate-900">Factures B2B générées</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Facture</th>
                        <th class="px-4 py-3 text-left">Entreprise</th>
                        <th class="px-4 py-3 text-left">Période</th>
                        <th class="px-4 py-3 text-left">Total</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                        <th class="px-4 py-3 text-left">Sites</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    @forelse($invoices as $invoice)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                {{ $invoice->invoice_number }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $invoice->organizationAccount?->name ?? '—' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $invoice->billing_period_start?->format('d/m/Y') }}
                                →
                                {{ $invoice->billing_period_end?->format('d/m/Y') }}
                            </td>

                            <td class="px-4 py-3 font-semibold text-slate-900">
                                {{ number_format((float) $invoice->total_amount, 2, ',', ' ') }}
                                {{ $invoice->currency }}
                            </td>

                            <td class="px-4 py-3">
                                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                                    {{ $invoice->status }}
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                @foreach((array) $invoice->site_breakdown as $site)
                                    <div class="text-xs text-slate-600">
                                        {{ $site['site'] ?? '—' }} :
                                        {{ $site['count'] ?? 0 }} RDV —
                                        {{ number_format((float) ($site['subtotal'] ?? 0), 2, ',', ' ') }} €
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">
                                Aucune facture B2B générée.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">
            {{ $invoices->links() }}
        </div>

        
    </div>
</x-page-shell>