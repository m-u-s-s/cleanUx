<section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 text-white shadow-sm">
    <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.35fr_0.75fr] lg:px-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-300">
                Espace finance client
            </p>

            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                {{ __('Documents & finance') }}
            </h1>

            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200 sm:text-base">
                Retrouvez vos devis, factures, paiements récents, statut d’abonnement et reste à payer dans une vue claire.
            </p>

            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('client.dashboard') }}"
                   class="rounded-2xl bg-white px-4 py-2 text-sm font-black text-slate-900 transition hover:bg-slate-100">
                    ← Retour dashboard
                </a>

                <a href="{{ route('client.rendezvous.index') }}"
                   class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                    📅 Mes rendez-vous
                </a>

                @if(! $subscriptionSummary['is_premium'])
                    <a href="{{ route('premium.offer') }}"
                       class="rounded-2xl bg-amber-400 px-4 py-2 text-sm font-black text-slate-900 transition hover:bg-amber-300">
                        ★ Découvrir Premium
                    </a>
                @endif
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">
                        Santé paiement
                    </p>

                    <h2 class="mt-2 text-xl font-black text-white">
                        {{ $paymentHealth['title'] }}
                    </h2>

                    <p class="mt-2 text-sm text-slate-200">
                        {{ $paymentHealth['message'] }}
                    </p>
                </div>

                <span class="rounded-full px-3 py-1 text-xs font-black
                    {{ $paymentHealth['tone'] === 'rose' ? 'bg-rose-400 text-white' : '' }}
                    {{ $paymentHealth['tone'] === 'amber' ? 'bg-amber-300 text-slate-900' : '' }}
                    {{ $paymentHealth['tone'] === 'emerald' ? 'bg-emerald-300 text-slate-900' : '' }}">
                    {{ $paymentHealth['label'] }}
                </span>
            </div>

            <div class="mt-5 rounded-2xl bg-white/10 p-4">
                <p class="text-sm text-slate-300">Reste à payer</p>
                <p class="mt-1 text-3xl font-black text-white">
                    {{ number_format((float) $financeSummary['outstanding_total'], 2, ',', ' ') }}
                    {{ $financeSummary['currency_symbol'] ?? '€' }}
                </p>

                @if($financeSummary['next_due_at'])
                    <p class="mt-1 text-xs text-slate-300">
                        Prochaine échéance : {{ optional($financeSummary['next_due_at'])->format('d/m/Y') }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</section>
