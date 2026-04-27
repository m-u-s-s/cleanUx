<x-page-shell
    title="💳 Mon portefeuille"
    subtitle="Consultez vos crédits, avoirs et compensations disponibles.">

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-1 rounded-2xl bg-blue-600 text-white p-6 shadow">
            <p class="text-sm opacity-80">Solde disponible</p>
            <p class="mt-2 text-4xl font-bold">
                {{ number_format($balance, 2, ',', ' ') }} €
            </p>
            <p class="mt-3 text-sm opacity-90">
                Ce montant peut être utilisé pour une prochaine réservation.
            </p>
        </div>

        <div class="md:col-span-2 rounded-2xl border bg-white p-6 shadow-sm">
            <h3 class="font-semibold text-slate-900">Comment ça fonctionne ?</h3>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                <div class="rounded-xl bg-slate-50 border p-4">
                    <p class="font-medium text-slate-800">1. Crédit ajouté</p>
                    <p class="text-slate-500 mt-1">Après un geste commercial ou un problème résolu.</p>
                </div>

                <div class="rounded-xl bg-slate-50 border p-4">
                    <p class="font-medium text-slate-800">2. Solde disponible</p>
                    <p class="text-slate-500 mt-1">Le crédit reste visible dans votre espace client.</p>
                </div>

                <div class="rounded-xl bg-slate-50 border p-4">
                    <p class="font-medium text-slate-800">3. Utilisation</p>
                    <p class="text-slate-500 mt-1">Il pourra être déduit d’une prochaine intervention.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border bg-white shadow-sm overflow-hidden">
        <div class="p-5 border-b">
            <h3 class="font-semibold text-slate-900">Historique des crédits</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Raison</th>
                        <th class="px-4 py-3 text-left">Montant</th>
                        <th class="px-4 py-3 text-left">Restant</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                        <th class="px-4 py-3 text-left">Expiration</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    @forelse($credits as $credit)
                        <tr>
                            <td class="px-4 py-3">
                                {{ $credit->created_at?->format('d/m/Y') }}
                            </td>

                            <td class="px-4 py-3">
                                {{ ucfirst($credit->type) }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $credit->reason ?? '—' }}
                            </td>

                            <td class="px-4 py-3 font-medium">
                                {{ number_format($credit->amount, 2, ',', ' ') }} €
                            </td>

                            <td class="px-4 py-3 font-medium text-blue-700">
                                {{ number_format($credit->remaining_amount, 2, ',', ' ') }} €
                            </td>

                            <td class="px-4 py-3">
                                <span class="rounded-full px-3 py-1 text-xs font-medium
                                    {{ $credit->status === 'active'
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-slate-100 text-slate-600' }}">
                                    {{ ucfirst($credit->status) }}
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                {{ $credit->expires_at?->format('d/m/Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                Aucun crédit disponible pour le moment.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">
            {{ $credits->links() }}
        </div>
    </div>
</x-page-shell>