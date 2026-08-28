<x-page-shell
    typed
    :eyebrow="__('Espace client')"
    :title="$salutation"
    :subtitle="$isPremium
        ? __('Votre espace premium centralise vos rendez-vous, vos favoris, vos documents et vos services disponibles.')
        : __('Votre espace client centralise vos rendez-vous, vos documents, vos services et vos prochaines interventions.')">

    <x-slot name="actions">
        @if(Route::has('client.rendezvous.create'))
            <a href="{{ route('client.rendezvous.create') }}" class="brio-btn-primary inline-flex items-center gap-2">
                <x-ui.icon name="plus" class="w-4 h-4" />
                <span>{{ __('Nouveau rendez-vous') }}</span>
            </a>
        @endif

        @if(Route::has('client.rendezvous.index'))
            <a href="{{ route('client.rendezvous.index') }}" class="brio-btn-secondary inline-flex items-center gap-2">
                <x-ui.icon name="calendar" class="w-4 h-4" />
                <span>{{ __('Mes rendez-vous') }}</span>
            </a>
        @endif

        @if(Route::has('client.finance'))
            <a href="{{ route('client.finance') }}" class="brio-btn-secondary inline-flex items-center gap-2">
                <x-ui.icon name="credit-card" class="w-4 h-4" />
                <span>{{ __('Finance') }}</span>
            </a>
        @endif

        @if(Route::has('client.subscriptions-v2'))
            <a href="{{ route('client.subscriptions-v2') }}" class="brio-btn-secondary inline-flex items-center gap-2">
                <x-ui.icon name="refresh" class="w-4 h-4" />
                <span>{{ __('Abonnements') }}</span>
            </a>
        @endif

        {{--
          `Route::has()` prouve que la porte existe, pas qu'on a la clé : le
          composant derrière refuse tout client qui n'est pas une société
          (`abort_unless(isClientCompany(), 403)`). Le bouton était donc offert à
          tous les particuliers, pour un 403 garanti au clic. On pose ici la même
          condition que la garde — la même méthode, pas une cousine.
        --}}
        @if(Route::has('client.analytics.dashboard') && auth()->user()?->isClientCompany())
            <a href="{{ route('client.analytics.dashboard') }}" class="brio-btn-secondary inline-flex items-center gap-2">
                <x-ui.icon name="chart-bar" class="w-4 h-4" />
                <span>{{ __('Analytics') }}</span>
            </a>
        @endif
    </x-slot>

    <div class="flex flex-wrap items-center gap-2">
        @if($isPremium)
            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                <x-ui.icon name="star" class="w-3 h-3" />
                {{ __('Premium') }}
            </span>
        @else
            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                {{ __('Standard') }}
            </span>
        @endif

        @if($activeSubscription)
            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                <x-ui.icon name="check-circle" class="w-3 h-3" />
                {{ __('Abonnement actif') }}
            </span>
        @endif

        @if(($accountContext['type_label'] ?? null) === 'Entreprise')
            <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">
                <x-ui.icon name="building-office" class="w-3 h-3" />
                {{ __('Compte entreprise') }}
            </span>
        @endif

        @if($accountContext['primary_zone'] ?? false)
            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700">
                <x-ui.icon name="map-pin" class="w-3 h-3" />
                {{ $accountContext['primary_zone'] }}
            </span>
        @endif
    </div>
</x-page-shell>
