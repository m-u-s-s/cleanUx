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
