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

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase text-slate-500">Crédits actifs</p>
                <p class="mt-2 text-2xl font-black text-emerald-700">
                    {{ $stats['active_count'] ?? 0 }}
                </p>
            </div>

            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase text-slate-500">Total reçu</p>
                <p class="mt-2 text-2xl font-black text-blue-700">
                    {{ number_format($stats['total_received'] ?? 0, 2, ',', ' ') }} €
                </p>
            </div>

            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase text-slate-500">Déjà utilisé</p>
                <p class="mt-2 text-2xl font-black text-indigo-700">
                    {{ number_format($stats['total_used'] ?? 0, 2, ',', ' ') }} €
                </p>
            </div>

            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase text-slate-500">Expirés</p>
                <p class="mt-2 text-2xl font-black text-red-700">
                    {{ $stats['expired_count'] ?? 0 }}
                </p>
            </div>
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
                            @php
                            $statusClass = match ($credit->status) {
                            'active' => 'bg-emerald-100 text-emerald-700',
                            'used' => 'bg-blue-100 text-blue-700',
                            'expired' => 'bg-red-100 text-red-700',
                            default => 'bg-slate-100 text-slate-600',
                            };

                            $statusLabel = match ($credit->status) {
                            'active' => 'Disponible',
                            'used' => 'Utilisé',
                            'expired' => 'Expiré',
                            default => ucfirst($credit->status),
                            };
                            @endphp

                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                        </td>


                        <td class="px-4 py-3">
                            @if($credit->expires_at)
                            <div>
                                <p>{{ $credit->expires_at->format('d/m/Y') }}</p>

                                @if($credit->status === 'active' && $credit->expires_at->isFuture() && $credit->expires_at->diffInDays(now()) <= 14)
                                    <p class="text-xs font-semibold text-amber-600">
                                    Expire bientôt
                                    </p>
                                    @elseif($credit->expires_at->isPast())
                                    <p class="text-xs font-semibold text-red-600">
                                        Expiré
                                    </p>
                                    @endif
                            </div>
                            @else
                            —
                            @endif
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