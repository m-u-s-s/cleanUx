<x-page-shell
        :eyebrow="__('Espace client')"
        :title="'Bonjour ' . \Illuminate\Support\Str::before(auth()->user()->name, ' ')"
        :subtitle="$isPremium
            ? __('Votre espace premium centralise vos rendez-vous, vos favoris, vos documents et vos services disponibles.')
            : __('Votre espace client centralise vos rendez-vous, vos documents, vos services et vos prochaines interventions.')">

        <x-slot name="actions">
            @if(Route::has('client.rendezvous.create'))
                <a href="{{ route('client.rendezvous.create') }}" class="cu-btn-primary">
                    {{ __('➕ Nouveau rendez-vous') }}
                </a>
            @endif

            @if(Route::has('client.rendezvous.index'))
                <a href="{{ route('client.rendezvous.index') }}" class="cu-btn-secondary">
                    {{ __('📅 Mes rendez-vous') }}
                </a>
            @endif

            @if(Route::has('client.finance'))
                <a href="{{ route('client.finance') }}" class="cu-btn-secondary">
                    {{ __('💳 Finance') }}
                </a>
            @endif

            @if(Route::has('client.subscriptions'))
                <a href="{{ route('client.subscriptions') }}" class="cu-btn-secondary">
                    {{ __('🔁 Abonnements') }}
                </a>
            @endif
        </x-slot>

        <div class="flex flex-wrap items-center gap-2">
            <span class="cu-chip {{ $isPremium ? '!border-amber-200 !bg-amber-50 !text-amber-700' : '' }}">
                {{ $isPremium ? __('★ Premium') : __('Standard') }}
            </span>

            @if($activeSubscription)
                <span class="cu-chip">{{ __('Abonnement actif') }}</span>
            @endif

            @if(($accountContext['type_label'] ?? null) === 'Entreprise')
                <span class="cu-chip">{{ __('Compte entreprise') }}</span>
            @endif

            @if($accountContext['primary_zone'] ?? false)
                <span class="cu-chip">
                    {{ __('Zone') }} : {{ $accountContext['primary_zone'] }}
                </span>
            @endif
        </div>
    </x-page-shell>
