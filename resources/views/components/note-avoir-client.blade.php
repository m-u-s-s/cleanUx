@props([
    // La réservation concernée. Sans avoir appliqué, la note ne s'affiche pas du tout.
    'booking' => null,
])

{{--
    UN AVOIR CLIENT A RÉDUIT LE PRIX DE CETTE MISSION.

    LE PRESTATAIRE DOIT L'APPRENDRE ICI, PAS EN COMPTANT SES VIREMENTS. Un avoir accordé par la
    plateforme réduit le prix de la mission, et la part du prestataire suit ce prix réduit : il
    touche donc moins que le tarif affiché au moment où il a accepté.

    C'est une décision assumée — le geste commercial est partagé entre la plateforme et le
    prestataire, au prorata. Ce qui ne serait PAS acceptable, c'est qu'il le découvre seul, six
    mois plus tard, en rapprochant ses relevés.

    La note lit l'instantané de prix, jamais un calcul refait ici : recalculer ferait diverger ce
    qui s'affiche et ce qui a été versé.
--}}

@php
    $instantane = (array) ($booking?->pricing_snapshot ?? []);
    $avoirApplique = (float) ($instantane['customer_credit_applied'] ?? 0);
    $prixApres = $instantane['devis_after_credit'] ?? null;
    $devise = $booking?->currency ?? config('fx.base_currency', 'EUR');
@endphp

@if($avoirApplique > 0)
    <div {{ $attributes->merge([
        'class' => 'rounded-xl border border-amber-300/60 bg-amber-50/60 px-3 py-2 text-xs '
            .'text-amber-900 dark:border-amber-700/50 dark:bg-amber-950/30 dark:text-amber-100',
    ]) }}>
        <p class="font-semibold">
            <span aria-hidden="true">🎟️</span>
            {{ __('Le client a réglé une partie avec un avoir') }}
        </p>

        <p class="mt-1">
            {{ __('Avoir appliqué :') }}
            <x-money :amount="$avoirApplique" :currency="$devise" />
            @if($prixApres !== null)
                · {{ __('prix de la mission ramené à') }}
                <x-money :amount="(float) $prixApres" :currency="$devise" />
            @endif
        </p>

        {{-- LA PHRASE QUI COMPTE : elle dit la conséquence, pas seulement le fait. --}}
        <p class="mt-1 opacity-90">
            {{ __('Votre part est calculée sur ce prix réduit : vous percevez donc moins que le tarif affiché avant l’avoir. La commission de la plateforme baisse dans la même proportion.') }}
        </p>

        {{ $slot }}
    </div>
@endif
