<div class="py-6">
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Abonnements</p>
                <h1 class="text-2xl font-black text-slate-900">Mes abonnements récurrents</h1>
                <p class="text-sm text-slate-500">Gérez vos forfaits cleaning hebdo, maintenance annuelle, etc.</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['mine' => 'Mes abos', 'plans' => 'Catalogue plans'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        @if($tab === 'mine')
            <div class="space-y-3">
                @forelse($this->mySubscriptions as $sub)
                    <div class="rounded-2xl border bg-white shadow-sm p-5">
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-lg font-bold text-slate-900">{{ $sub->plan?->name }}</h2>
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $sub->status === 'active',
                                        'bg-blue-100 text-blue-800' => $sub->status === 'trialing',
                                        'bg-amber-100 text-amber-800' => in_array($sub->status, ['paused', 'past_due']),
                                        'bg-red-100 text-red-800' => $sub->status === 'cancelled',
                                        'bg-slate-100 text-slate-800' => $sub->status === 'expired',
                                    ])>{{ $sub->status }}</span>
                                </div>
                                <p class="font-mono text-xs text-slate-500 mt-1">{{ $sub->code }}</p>
                                <p class="text-sm text-slate-600 mt-2">{{ $sub->plan?->description }}</p>
                                <dl class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                                    <div>
                                        <dt class="font-semibold text-slate-500 uppercase">Prix</dt>
                                        <dd class="font-mono">{{ number_format($sub->plan?->price_cents / 100, 2, ',', ' ') }} {{ $sub->billing_currency }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-500 uppercase">Période</dt>
                                        <dd>{{ $sub->plan?->billing_period }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-500 uppercase">Prochaine facture</dt>
                                        <dd>{{ optional($sub->next_billing_at)->format('d/m/Y') ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-500 uppercase">Total facturé</dt>
                                        <dd class="font-mono">{{ number_format($sub->total_billed_cents / 100, 2, ',', ' ') }} €</dd>
                                    </div>
                                </dl>
                                @if($sub->trial_ends_at && $sub->trial_ends_at->isFuture())
                                    <p class="mt-2 text-xs text-blue-600">Période d'essai jusqu'au {{ $sub->trial_ends_at->format('d/m/Y') }}.</p>
                                @endif
                                @if($sub->cancel_at_period_end)
                                    <p class="mt-2 text-xs text-amber-700">Annulation prévue le {{ optional($sub->ends_at)->format('d/m/Y') }}.</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-2 md:flex-col md:items-end shrink-0">
                                <button wire:click="showDetails({{ $sub->id }})" class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50">Détails</button>
                                @if($sub->status === 'active')
                                    <button wire:click="pause({{ $sub->id }})" class="rounded-xl border border-amber-300 text-amber-700 px-3 py-1.5 text-xs font-semibold hover:bg-amber-50">Pause</button>
                                @endif
                                @if($sub->status === 'paused')
                                    <button wire:click="resume({{ $sub->id }})" class="rounded-xl border border-emerald-300 text-emerald-700 px-3 py-1.5 text-xs font-semibold hover:bg-emerald-50">Reprendre</button>
                                @endif
                                @if(! in_array($sub->status, ['cancelled', 'expired']) && ! $sub->cancel_at_period_end)
                                    <button wire:click="cancelAtPeriodEnd({{ $sub->id }})"
                                            class="rounded-xl border border-slate-300 text-slate-700 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50"
                                            onclick="return confirm('Annuler à la fin de la période en cours ?')">
                                        Annuler en fin de période
                                    </button>
                                    <button wire:click="cancelImmediately({{ $sub->id }})"
                                            class="rounded-xl border border-red-300 text-red-700 px-3 py-1.5 text-xs font-semibold hover:bg-red-50"
                                            onclick="return confirm('Annuler IMMÉDIATEMENT (sans remboursement de la période en cours) ?')">
                                        Annuler maintenant
                                    </button>
                                @endif
                            </div>
                        </div>

                        @if($detailsForSubId === $sub->id)
                            <div class="mt-4 border-t pt-4">
                                <p class="text-xs font-bold uppercase text-slate-500 mb-2">Historique cycles</p>
                                <div class="rounded-xl border bg-slate-50 overflow-x-auto">
                                    <table class="min-w-full text-xs">
                                        <thead class="bg-slate-100 text-slate-500">
                                            <tr>
                                                <th class="px-3 py-2 text-left">#</th>
                                                <th class="px-3 py-2 text-left">Période</th>
                                                <th class="px-3 py-2 text-left">Montant</th>
                                                <th class="px-3 py-2 text-left">Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            @forelse($cycles as $c)
                                                <tr>
                                                    <td class="px-3 py-2 font-mono">{{ $c->cycle_number }}</td>
                                                    <td class="px-3 py-2 text-slate-500">{{ optional($c->period_start)->format('d/m') }} → {{ optional($c->period_end)->format('d/m') }}</td>
                                                    <td class="px-3 py-2 font-mono">{{ number_format($c->planned_amount_cents / 100, 2, ',', ' ') }} €</td>
                                                    <td class="px-3 py-2">
                                                        <span @class([
                                                            'rounded-full px-2 py-0.5 text-xs font-semibold',
                                                            'bg-emerald-100 text-emerald-800' => $c->billing_status === 'paid',
                                                            'bg-amber-100 text-amber-800' => in_array($c->billing_status, ['pending', 'invoiced']),
                                                            'bg-red-100 text-red-800' => $c->billing_status === 'failed',
                                                            'bg-slate-100 text-slate-800' => $c->billing_status === 'skipped',
                                                        ])>{{ $c->billing_status }}</span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="px-3 py-6 text-center text-slate-400">Aucun cycle.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center">
                        <p class="text-slate-500">Vous n'avez pas encore d'abonnement.</p>
                        <button wire:click="$set('tab', 'plans')" class="mt-3 rounded-xl bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-700">
                            Découvrir les plans
                        </button>
                    </div>
                @endforelse
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @forelse($this->plans as $plan)
                    <div class="rounded-2xl border bg-white shadow-sm p-5 flex flex-col">
                        <h3 class="text-lg font-bold text-slate-900">{{ $plan->name }}</h3>
                        <p class="text-sm text-slate-600 mt-1">{{ $plan->description }}</p>
                        <p class="mt-4 text-3xl font-black text-indigo-600">
                            {{ number_format($plan->price_cents / 100, 0, ',', ' ') }} {{ $plan->currency }}
                            <span class="text-sm font-normal text-slate-500">/ {{ $plan->billing_period }}</span>
                        </p>
                        @if($plan->trial_days > 0)
                            <p class="text-xs text-blue-600 mt-1">{{ $plan->trial_days }} jours d'essai gratuit</p>
                        @endif
                        @if($plan->included_units_per_cycle > 0)
                            <p class="text-xs text-slate-600 mt-2">
                                Inclus : {{ $plan->included_units_per_cycle }} {{ $plan->included_unit_type }} / période
                            </p>
                        @endif
                        @if($plan->features)
                            <ul class="mt-3 space-y-1 text-xs text-slate-700">
                                @foreach($plan->features as $key => $value)
                                    @if($value === true)
                                        <li>✓ {{ str_replace('_', ' ', $key) }}</li>
                                    @elseif(is_numeric($value))
                                        <li>✓ {{ str_replace('_', ' ', $key) }} : {{ $value }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                        <button wire:click="subscribe('{{ $plan->code }}')"
                                class="mt-auto pt-4 self-stretch">
                            <span class="block rounded-xl bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-700 text-center">
                                Souscrire
                            </span>
                        </button>
                    </div>
                @empty
                    <p class="col-span-3 text-center text-slate-400 py-12">Aucun plan disponible.</p>
                @endforelse
            </div>
        @endif

    </div>
</div>
