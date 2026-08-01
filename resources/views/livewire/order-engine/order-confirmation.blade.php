{{--
    Le dernier écran : récapitulatif, devis, confirmation.

    Le prix est visible AVANT toute demande d'identité, y compris pour un visiteur non connecté.
    L'action principale vit en bas, dans la zone du pouce. Ce qui bloque est écrit en toutes
    lettres : un bouton grisé muet est un cul-de-sac.
--}}
<div class="pb-32 lg:pb-8">
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-6">

        @if (! $this->draft())
            {{-- Panier vide : jamais un cul-de-sac, toujours une porte de sortie. --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center">
                <h1 class="text-xl font-semibold text-slate-900">Votre panier est vide</h1>
                <p class="mt-2 text-sm text-slate-500">Choisissez un service pour obtenir une estimation immédiate.</p>
                <a href="{{ route('order.journey') }}" wire:navigate
                    class="mt-6 inline-flex min-h-[48px] items-center rounded-xl bg-slate-900 px-6 text-sm font-medium text-white">
                    Composer une commande
                </a>
            </div>
        @else
            @php($confirmed = $this->draft()->status === \App\Support\Domain\OrderDraftStatus::CONVERTED)

            @if ($confirmed)
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                    <h1 class="text-xl font-semibold text-emerald-900">Commande confirmée</h1>
                    <p class="mt-1 text-sm text-emerald-800">
                        Référence {{ $this->draft()->reference }} — conservez-la, elle se dicte au support.
                    </p>
                </div>
            @else
                <div>
                    <h1 class="text-2xl font-semibold leading-tight text-slate-900">Votre commande</h1>
                    <p class="mt-1 text-sm text-slate-500">Vérifiez, puis confirmez. Rien n’est débité maintenant.</p>
                </div>
            @endif

            {{-- ─── Où et quand ─────────────────────────────────────────────────────────── --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="lieu-titre">
                <h2 id="lieu-titre" class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                    Où et quand
                </h2>

                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex gap-3">
                        <dt class="w-24 shrink-0 text-slate-500">Adresse</dt>
                        <dd class="min-w-0 text-slate-900">{{ $this->draft()->address ?: '—' }}</dd>
                    </div>
                    <div class="flex gap-3">
                        <dt class="w-24 shrink-0 text-slate-500">Date</dt>
                        <dd class="min-w-0 text-slate-900">
                            {{ $this->draft()->scheduled_at?->translatedFormat('l j F Y, H\\hi') ?: 'Dès que possible' }}
                        </dd>
                    </div>
                </dl>

                @unless ($confirmed)
                    <a href="{{ route('order.journey') }}" wire:navigate
                        class="mt-4 inline-block text-sm font-medium text-slate-500 underline underline-offset-4 hover:text-slate-900">
                        Modifier
                    </a>
                @endunless
            </section>

            {{-- ─── Le devis, détaillé ──────────────────────────────────────────────────── --}}
            @if ($this->quote())
                <section class="rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="devis-titre">
                    <h2 id="devis-titre" class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                        Détail du devis
                    </h2>

                    <div class="mt-4 space-y-4">
                        @foreach ($this->quote()['items'] as $line)
                            <details class="group rounded-xl border border-slate-100 bg-slate-50/60 p-4" @if ($loop->first) open @endif>
                                <summary class="flex cursor-pointer items-baseline justify-between gap-3 text-sm font-medium text-slate-900">
                                    <span class="min-w-0 truncate">{{ $line['trade']->name }}</span>
                                    <span class="shrink-0 tabular-nums">
                                        @if ($line['quote']->quoteOnly)
                                            Sur devis
                                        @elseif ($line['quote']->isExact())
                                            {{ number_format($line['quote']->minCents / 100, 0, ',', ' ') }} €
                                        @else
                                            {{ number_format($line['quote']->minCents / 100, 0, ',', ' ') }}–{{ number_format($line['quote']->maxCents / 100, 0, ',', ' ') }} €
                                        @endif
                                    </span>
                                </summary>

                                {{-- Chaque ligne est explicable : c'est ce qui rend le prix acceptable. --}}
                                <ul class="mt-3 space-y-1.5 border-t border-slate-200 pt-3 text-sm">
                                    @foreach ($line['quote']->lines as $detail)
                                        <li class="flex items-baseline justify-between gap-3">
                                            <span class="min-w-0 text-slate-600">{{ $detail['label'] }}</span>
                                            <span class="shrink-0 tabular-nums text-slate-700">
                                                {{ number_format($detail['min_cents'] / 100, 0, ',', ' ') }} €
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endforeach
                    </div>

                    <div class="mt-5 flex items-baseline justify-between border-t border-slate-200 pt-4">
                        <span class="text-sm font-medium text-slate-900">Total estimé</span>
                        <span class="text-2xl font-semibold tabular-nums text-slate-900">
                            @if ($this->quote()['order']->quoteOnly)
                                Sur devis
                            @elseif ($this->quote()['order']->isExact())
                                {{ number_format($this->quote()['order']->minCents / 100, 0, ',', ' ') }} €
                            @else
                                {{ number_format($this->quote()['order']->minCents / 100, 0, ',', ' ') }}
                                – {{ number_format($this->quote()['order']->maxCents / 100, 0, ',', ' ') }} €
                            @endif
                        </span>
                    </div>

                    @unless ($this->quote()['order']->isExact() || $this->quote()['order']->quoteOnly)
                        <p class="mt-2 text-xs text-slate-500">
                            Vous êtes engagé sur le montant le plus bas de la fourchette. L’écart éventuel se
                            règle après l’intervention, sur constat.
                        </p>
                    @endunless
                </section>
            @endif

            {{-- ─── Après confirmation : les réservations et leur paiement ──────────────── --}}
            @if ($confirmed)
                <section class="rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="suivi-titre">
                    <h2 id="suivi-titre" class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                        Vos interventions
                    </h2>

                    <ul class="mt-4 space-y-3">
                        @foreach ($this->bookings() as $booking)
                            @php($state = $this->paymentStates()[$booking->id] ?? ['ready' => false, 'reason' => null])
                            <li class="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="text-sm font-medium text-slate-900">{{ $booking->booking_reference }}</span>
                                    <span class="shrink-0 text-sm tabular-nums text-slate-700">
                                        {{ $booking->estimated_price ? number_format((float) $booking->estimated_price, 0, ',', ' ').' €' : 'Sur devis' }}
                                    </span>
                                </div>

                                @if ($state['ready'])
                                    <a href="{{ route('client.booking.checkout', $booking->id) }}"
                                        class="mt-3 inline-flex min-h-[48px] w-full items-center justify-center rounded-xl bg-slate-900 text-sm font-medium text-white">
                                        Autoriser le paiement
                                    </a>
                                @else
                                    {{-- Le paiement attend, et on DIT pourquoi. --}}
                                    <p class="mt-2 text-sm text-slate-600">{{ $state['reason'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    <a href="{{ route('client.dashboard') }}"
                        class="mt-4 inline-block text-sm font-medium text-slate-500 underline underline-offset-4 hover:text-slate-900">
                        Suivre mes interventions
                    </a>
                </section>
            @endif

            @if ($error)
                <p class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">{{ $error }}</p>
            @endif

            {{-- ─── L'identité, au dernier moment ───────────────────────────────────────── --}}
            @unless ($confirmed)
                <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur lg:static lg:border-0 lg:bg-transparent lg:px-0 lg:backdrop-blur-none">
                    <div class="mx-auto max-w-3xl">

                        @if ($this->blockers())
                            {{-- Ce qui manque, en toutes lettres, avant le bouton. --}}
                            <ul class="mb-3 space-y-1 text-sm text-amber-900" role="alert">
                                @foreach ($this->blockers() as $blocker)
                                    <li>{{ $blocker }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @auth
                            <button type="button" wire:click="confirm" wire:loading.attr="disabled"
                                @disabled(count($this->blockers()) > 0)
                                class="min-h-[52px] w-full rounded-xl bg-slate-900 text-base font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300">
                                <span wire:loading.remove wire:target="confirm">Confirmer la commande</span>
                                <span wire:loading wire:target="confirm">Confirmation…</span>
                            </button>
                        @else
                            <p class="mb-3 text-sm text-slate-600">
                                Dernière étape : votre compte, pour suivre l’intervention et retrouver votre facture.
                            </p>
                            <div class="flex gap-3">
                                <button type="button" wire:click="goToLogin('register')"
                                    class="min-h-[52px] flex-1 rounded-xl bg-slate-900 text-sm font-medium text-white">
                                    Créer un compte
                                </button>
                                <button type="button" wire:click="goToLogin('login')"
                                    class="min-h-[52px] flex-1 rounded-xl border border-slate-300 bg-white text-sm font-medium text-slate-900">
                                    Se connecter
                                </button>
                            </div>
                            <p class="mt-2 text-center text-xs text-slate-500">Votre panier est conservé.</p>
                        @endauth
                    </div>
                </div>
            @endunless
        @endif
    </div>
</div>
