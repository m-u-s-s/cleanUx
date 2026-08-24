@php
    $comparatif = $budget['subscription_vs_on_demand'];
    $maxMois = collect($budget['by_month'])->max('total_cents') ?: 1;
@endphp

<div class="mx-auto max-w-3xl px-4 py-8">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Mon budget entretien</h1>
        <p class="mt-1 text-sm text-slate-500">
            Ce que vous engagez, par mois et par métier.
        </p>
    </header>

    {{-- Les trois chiffres qui répondent d'un coup d'œil --}}
    <div class="mb-8 grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">
                <x-money :amount="(float) ($budget['total_cents'] / 100)" />
            </p>
            <p class="text-xs text-slate-500">{{ $budget['bookings_count'] }} intervention(s)</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Par mois</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">
                <x-money :amount="(float) ($budget['monthly_average_cents'] / 100)" />
            </p>
            {{-- Calculée sur les mois où il s'est passé quelque chose : diviser par douze un
                 client arrivé en octobre lui montrerait une moyenne qu'il ne reconnaît pas. --}}
            <p class="text-xs text-slate-500">sur vos mois actifs</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Période</p>
            <label class="mt-1 block">
                <select wire:model.live="mois"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm font-semibold text-slate-900">
                    <option value="3">3 mois</option>
                    <option value="6">6 mois</option>
                    <option value="12">12 mois</option>
                    <option value="24">24 mois</option>
                </select>
            </label>
        </div>
    </div>

    {{-- Abonnement contre à la demande : le seul chiffre qui serve à décider --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500">
            Abonnement ou à la demande
        </h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-900">Interventions récurrentes</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-slate-900">
                    <x-money :amount="(float) ($comparatif['subscription']['total_cents'] / 100)" />
                </p>
                <p class="text-xs text-slate-500">
                    {{ $comparatif['subscription']['bookings_count'] }} intervention(s) —
                    <x-money :amount="(float) ($comparatif['subscription']['average_cents'] / 100)" /> en moyenne
                </p>
            </div>

            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-900">Interventions ponctuelles</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-slate-900">
                    <x-money :amount="(float) ($comparatif['on_demand']['total_cents'] / 100)" />
                </p>
                <p class="text-xs text-slate-500">
                    {{ $comparatif['on_demand']['bookings_count'] }} intervention(s) —
                    <x-money :amount="(float) ($comparatif['on_demand']['average_cents'] / 100)" /> en moyenne
                </p>
            </div>
        </div>

        @if ($comparatif['subscription']['bookings_count'] > 0 && $comparatif['on_demand']['bookings_count'] > 0)
        @php
            $ecart = $comparatif['on_demand']['average_cents'] - $comparatif['subscription']['average_cents'];
        @endphp
        <p class="mt-4 text-sm {{ $ecart > 0 ? 'text-emerald-700' : 'text-slate-600' }}">
            @if ($ecart > 0)
            Vos interventions récurrentes vous coûtent
            <x-money :amount="(float) ($ecart / 100)" /> de moins en moyenne.
            @else
            Vos interventions ponctuelles reviennent au même, voire moins cher.
            @endif
        </p>
        @elseif ($comparatif['subscription']['bookings_count'] === 0)
        <p class="mt-4 text-sm text-slate-500">
            Vous n'avez aucune intervention récurrente sur cette période.
        </p>
        @endif
    </div>

    {{-- Par mois --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500">Par mois</h2>

        @forelse ($budget['by_month'] as $ligne)
        <div class="mb-3 last:mb-0">
            <div class="flex items-center justify-between text-sm">
                <span class="text-slate-700">
                    {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $ligne['month'])->translatedFormat('F Y') }}
                </span>
                <span class="font-semibold tabular-nums text-slate-900">
                    <x-money :amount="(float) ($ligne['total_cents'] / 100)" />
                </span>
            </div>
            {{-- Une barre plutôt qu'un graphique : la tendance se lit, et rien à charger. --}}
            <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-slate-900"
                    style="width: {{ max(2, (int) round($ligne['total_cents'] / $maxMois * 100)) }}%"></div>
            </div>
        </div>
        @empty
        <p class="py-6 text-center text-sm text-slate-500">
            Aucune intervention sur cette période.
        </p>
        @endforelse
    </div>

    {{-- Par métier --}}
    <div class="rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Par métier
        </h2>

        @forelse ($budget['by_trade'] as $ligne)
        <div class="flex items-center justify-between border-b border-slate-50 px-5 py-3 last:border-0">
            <div>
                <p class="text-sm font-semibold text-slate-900">{{ $ligne['trade'] }}</p>
                <p class="text-xs text-slate-500">{{ $ligne['bookings_count'] }} intervention(s)</p>
            </div>
            <span class="text-sm font-semibold tabular-nums text-slate-900">
                <x-money :amount="(float) ($ligne['total_cents'] / 100)" />
            </span>
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-slate-500">Rien à afficher.</p>
        @endforelse
    </div>
</div>
