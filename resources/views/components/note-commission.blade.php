@props([
    'module' => \App\Models\CommissionRule::MODULE_PRESTATION,
    'typeDeBien' => null,
    'metier' => null,
    'zone' => null,
    'duree' => null,
    // Le montant à illustrer, en centimes. `null` = pas d'exemple chiffré.
    'montantCents' => null,
    'ton' => 'discret',
])

{{--
    LA NOTE DE COMMISSION — le taux tel qu'il s'applique MAINTENANT.

    Elle interroge le résolveur à CHAQUE rendu. Recopier un chiffre dans une vue, ou le passer
    depuis un composant, ferait mentir la page le jour où le taux change : c'est exactement le
    défaut que ce socle existe pour supprimer.

    Elle dit aussi D'OÙ vient le taux. Un pourcentage sans origine ne se conteste pas — et un
    prestataire qui ne peut pas contester un prélèvement finit par partir.
--}}

@php
    $resolution = app(\App\Services\Commission\ResolveurDeCommission::class)->pour(
        new \App\Services\Commission\ContexteDeCommission(
            module: $module,
            typeDeBien: $typeDeBien,
            tradeId: $metier,
            zoneId: $zone,
            dureeJours: $duree,
        ),
    );

    $gratuit = $resolution->taux <= 0.0;

    // L'EXEMPLE CHIFFRÉ PASSE PAR LE MÊME PARTAGE QUE L'ARGENT RÉEL. Le recalculer ici à la
    // main ferait diverger la note et la facture au premier plancher de commission.
    $exemple = $montantCents === null ? null : app(\App\Services\Payments\CommissionService::class)
        ->calculateForAmount(
            (int) $montantCents,
            null,
            null,
            null,
            new \App\Services\Commission\ContexteDeCommission(
                module: $module,
                typeDeBien: $typeDeBien,
                tradeId: $metier,
                zoneId: $zone,
                dureeJours: $duree,
            ),
        );
@endphp

<div {{ $attributes->merge([
        'class' => 'rounded-xl border px-3 py-2 text-xs '
            .($gratuit
                ? 'border-emerald-300/60 bg-emerald-50/60 text-emerald-900 dark:border-emerald-700/50 dark:bg-emerald-950/30 dark:text-emerald-100'
                : 'border-slate-200/70 bg-slate-50/70 text-slate-700 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-200'),
    ]) }}>

    <p class="font-semibold">
        <span aria-hidden="true">{{ $gratuit ? '🎁' : '⚖️' }}</span>
        {{ $resolution->note() }}
    </p>

    @if($exemple !== null)
        <p class="mt-1 tabular-nums">
            {{-- LE SYMBOLE VIENT DE LA DEVISE DU MONTANT, jamais du gabarit : la plateforme
                 encaisse en EUR comme en MAD, et un « € » ecrit ici mentirait au Maroc. --}}
            {{ __('Sur') }}
            <x-money :amount="$exemple['total_cents'] / 100" :currency="$exemple['currency']" />,
            {{ __('vous recevez') }}
            <x-money :amount="$exemple['provider_payout_cents'] / 100" :currency="$exemple['currency']" />.

            @if($exemple['minimum_applied'])
                <span class="block opacity-80">
                    {{ __('Le plancher de commission s’applique sur ce montant : le prélèvement réel est de :taux %.', [
                        'taux' => rtrim(rtrim(number_format($exemple['effective_commission_rate'] * 100, 2, ',', ' '), '0'), ','),
                    ]) }}
                </span>
            @endif
        </p>
    @endif

    @if($slot->isNotEmpty())
        <p class="mt-1 opacity-80">{{ $slot }}</p>
    @endif
</div>
