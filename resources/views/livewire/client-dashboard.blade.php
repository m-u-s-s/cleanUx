<div class="space-y-6">
    <x-active-sessions />

    <a href="{{ route('client.subscriptions') }}"
        class="bg-purple-600 text-white px-4 py-2 rounded-xl">
        🔁 Abonnements
    </a>
    
    <x-page-shell
        :eyebrow="__('Espace client')"
        :title="'Bonjour ' . \Illuminate\Support\Str::before(auth()->user()->name, ' ')"
        :subtitle="$isPremium
            ? __('Profitez de vos avantages premium et gérez vos prestations avec une expérience plus personnalisée.')
            : __('Gérez facilement vos prestations, votre historique et vos prochaines interventions.')">
        <x-slot name="actions">
            <a href="{{ route('client.rendezvous.create') }}" class="cu-btn-primary">{{ __('➕ Nouveau rendez-vous') }}</a>
            <a href="{{ route('client.rendezvous.index') }}" class="cu-btn-secondary">{{ __('📅 Mes rendez-vous') }}</a>
            <a href="{{ route('client.historique') }}" class="cu-btn-secondary">{{ __('🕘 Historique') }}</a>
            <a href="{{ route('client.finance') }}" class="cu-btn-secondary">{{ __('💳 Documents & finance') }}</a>
            @if($isPremium && count($favoriteEmployes))
            <a href="{{ route('client.favorite-employes') }}" class="cu-btn-secondary !border-amber-200 !bg-amber-50 !text-amber-700">{{ __('★ Mes favoris') }}</a>
            @endif
        </x-slot>

        <div class="flex flex-wrap items-center gap-2">
            <span class="cu-chip {{ $isPremium ? '!border-amber-200 !bg-amber-50 !text-amber-700' : '' }}">
                {{ $isPremium ? __('★ Premium') : __('Standard') }}
            </span>

            @if($activeSubscription)
            <span class="cu-chip">{{ __('Abonnement actif') }}</span>
            @endif

            @if(method_exists(auth()->user(), 'isCompany') && auth()->user()->isCompany())
            <span class="cu-chip">{{ __('Compte entreprise') }}</span>
            @endif
        </div>
    </x-page-shell>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi-card :title="__('Total prestations')" :value="$statsClient['total']" tone="slate" icon="📦" />
        <x-kpi-card :title="__('À venir')" :value="$statsClient['avenir']" tone="blue" icon="📅" />
        <x-kpi-card :title="__('Terminées')" :value="$statsClient['termine']" tone="green" icon="✅" />
        <x-kpi-card :title="__('Feedbacks laissés')" :value="$statsClient['feedbacks']" tone="amber" icon="⭐" />
    </div>

    <div wire:loading.flex class="cu-card items-center gap-4 p-6">
        <x-skeleton-block height="h-12" width="w-12" rounded="rounded-2xl" />
        <div class="flex-1 space-y-3">
            <x-skeleton-block width="w-40" />
            <x-skeleton-block height="h-5" width="w-80" />
            <x-skeleton-block width="w-56" />
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3" wire:loading.remove>
        <div class="xl:col-span-2">
            <x-app-card padding="p-6" :title="__('Prochain rendez-vous')" :subtitle="__('Votre prochain service planifié et les actions rapides associées.')">
                @if($prochainRendezVous)
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge :status="$prochainRendezVous->status" />
                        <x-priority-badge :priority="$prochainRendezVous->priorite" />
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="cu-card-muted p-4">
                        <p class="text-sm text-slate-500">{{ __('Service') }}</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">
                            {{ $prochainRendezVous->service_display_name }}
                        </p>
                    </div>

                    <div class="cu-card-muted p-4">
                        <p class="text-sm text-slate-500">{{ __('Date & heure') }}</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">
                            {{ $prochainRendezVous->date }} à {{ substr((string) $prochainRendezVous->heure, 0, 5) }}
                        </p>
                    </div>

                    <div class="cu-card-muted p-4">
                        <p class="text-sm text-slate-500">{{ __('Employé') }}</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">
                            {{ $prochainRendezVous->employe->name ?? __('À confirmer par notre équipe') }}
                        </p>
                    </div>

                    <div class="cu-card-muted p-4">
                        <p class="text-sm text-slate-500">{{ __('Adresse') }}</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">
                            {{ $prochainRendezVous->adresse ?? '—' }}, {{ $prochainRendezVous->ville ?? '—' }}
                        </p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('client.rendezvous.index') }}" class="cu-btn-primary">{{ __('Voir le détail') }}</a>

                    @if(!in_array($prochainRendezVous->status, ['en_route', 'sur_place', 'termine', 'refuse']))
                    <button type="button" wire:click="modifier({{ $prochainRendezVous->id }})" class="cu-btn-secondary">
                        {{ __('Modifier') }}
                    </button>
                    <button type="button" wire:click="annuler({{ $prochainRendezVous->id }})" class="cu-btn-danger">
                        {{ __('Annuler') }}
                    </button>
                    @endif
                </div>
                @else
                <x-empty-state
                    :title="__('Aucun rendez-vous à venir')"
                    :message="__('Planifiez une nouvelle prestation en quelques clics pour garder un suivi clair de vos interventions.')"
                    icon="📅">
                    <a href="{{ route('client.rendezvous.create') }}" class="cu-btn-primary">{{ __('Réserver maintenant') }}</a>
                </x-empty-state>
                @endif
            </x-app-card>
        </div>

        <div class="space-y-6">
            @if($isPremium)
            <x-app-card padding="p-6" :title="__('Abonnement Premium')" :subtitle="__('Vos avantages et votre statut d’abonnement.')">
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">{{ __('Statut') }}</span>
                        <span class="font-semibold text-emerald-700">{{ __('Actif') }}</span>
                    </div>

                    @if(auth()->user()->premium_renewal_at)
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">{{ __('Renouvellement') }}</span>
                        <span class="font-semibold text-slate-800">{{ optional(auth()->user()->premium_renewal_at)->format('d/m/Y') }}</span>
                    </div>
                    @endif
                </div>

                <div class="mt-5 cu-card-muted p-4 !border-amber-100 !bg-amber-50">
                    <p class="text-sm font-semibold text-amber-800">{{ __('Vos avantages') }}</p>
                    <ul class="mt-2 space-y-2 text-sm text-amber-700">
                        <li>{{ __('• Choix des employés favoris') }}</li>
                        <li>{{ __('• Visibilité sur les disponibilités') }}</li>
                        <li>{{ __('• Expérience plus personnalisée') }}</li>
                    </ul>
                </div>
            </x-app-card>
            @else
            <x-app-card padding="p-6" :title="__('Offre Premium mensuelle')" :subtitle="__('Montez en gamme avec une expérience plus personnalisée.')">
                <ul class="space-y-2 text-sm text-slate-600">
                    <li>{{ __('• Choisissez vos employés favoris') }}</li>
                    <li>{{ __('• Consultez leurs disponibilités') }}</li>
                    <li>{{ __('• Réservez avec une expérience plus personnalisée') }}</li>
                </ul>

                <a href="{{ route('premium.offer') }}" class="cu-btn-primary mt-5 !bg-amber-500 hover:!bg-amber-600">{{ __('Découvrir l’offre Premium') }}</a>
            </x-app-card>
            @endif

            <x-app-card padding="p-6" :title="__('Documents & finance')" :subtitle="__('Suivez vos devis, factures et votre reste à payer.')">
                <div class="grid grid-cols-2 gap-3">
                    <div class="cu-card-muted p-4">
                        <p class="text-sm text-slate-500">{{ __('Devis') }}</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $financeSnapshot['quotes_count'] }}</p>
                    </div>
                    <div class="cu-card-muted p-4">
                        <p class="text-sm text-slate-500">{{ __('Factures') }}</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $financeSnapshot['invoices_count'] }}</p>
                    </div>
                    <div class="cu-card-muted p-4">
                        <p class="text-sm text-slate-500">{{ __('En retard') }}</p>
                        <p class="mt-1 text-2xl font-bold text-rose-700">{{ $financeSnapshot['overdue_count'] }}</p>
                    </div>
                    <div class="cu-card-muted p-4">
                        <p class="text-sm text-slate-500">{{ __('Reste à payer') }}</p>
                        <p class="mt-1 text-lg font-bold text-emerald-700">{{ number_format((float) $financeSnapshot['outstanding_total'], 2, ',', ' ') }} €</p>
                    </div>
                </div>

                <div class="mt-5">
                    <a href="{{ route('client.finance') }}" class="cu-btn-primary">{{ __('Voir mes documents') }}</a>
                </div>
            </x-app-card>

            <x-app-card padding="p-6" :title="__('Adresses récentes')" :subtitle="__('Relancez plus vite vos prestations depuis vos adresses utilisées récemment.')">
                <div class="space-y-3">
                    @forelse($adressesRecentes as $adresse)
                    <div class="cu-list-item flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                        <div>
                            <p class="font-semibold text-slate-800">{{ $adresse->adresse }}</p>
                            <p class="text-sm text-slate-500">{{ $adresse->ville ?? '—' }} {{ $adresse->code_postal ?? '' }}</p>
                        </div>

                        <a href="{{ route('client.rendezvous.create', ['adresse' => $adresse->adresse, 'ville' => $adresse->ville, 'code_postal' => $adresse->code_postal]) }}"
                            class="cu-btn-secondary">
                            {{ __('Utiliser') }}
                        </a>
                    </div>
                    @empty
                    <x-empty-state :title="__('Aucune adresse récente')" :message="__('Vos dernières adresses de prestation apparaîtront ici pour accélérer vos prochaines réservations.')" icon="📍" />
                    @endforelse
                </div>
            </x-app-card>


            <x-app-card padding="p-6" :title="__('Couverture & services')" :subtitle="__('Votre zone principale et les services disponibles selon votre adresse.')">
                <div class="space-y-4">
                    <div class="cu-card-muted p-4">
                        <p class="text-sm text-slate-500">{{ __('Zone principale') }}</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">{{ $accountContext['primary_zone'] ?? __('Non définie') }}</p>
                        @if(($accountContext['zone_count'] ?? 0) > 1)
                        <p class="mt-1 text-xs text-slate-500">{{ $accountContext['zone_count'] }} zone(s) couverte(s)</p>
                        @endif
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-slate-800">{{ __('Services disponibles') }}</p>
                        <div class="mt-3 space-y-3">
                            @forelse($availableServices as $service)
                            <div class="cu-list-item flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $service['name'] }}</p>
                                    <p class="text-sm text-slate-500">{{ $service['zone_name'] ?: __('Zone couverte') }}</p>
                                </div>
                                <div class="text-right">
                                    @if(! is_null($service['base_price']))
                                    <p class="text-sm font-semibold text-slate-900">{{ number_format((float) $service['base_price'], 2, ',', ' ') }} €</p>
                                    @endif
                                    @if($service['requires_manual_validation'])
                                    <p class="text-xs text-amber-700">Validation manuelle</p>
                                    @endif
                                </div>
                            </div>
                            @empty
                            <x-empty-state :title="__('Aucun service disponible')" :message="__('Les services disponibles selon votre zone apparaîtront ici.')" icon="🗺️" />
                            @endforelse
                        </div>
                    </div>
                </div>
            </x-app-card>

        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2" wire:loading.remove>
        <div class="overflow-hidden rounded-[28px] bg-gradient-to-r from-slate-950 via-slate-800 to-slate-700 p-6 text-white shadow-[0_18px_50px_rgba(15,23,42,0.14)]">
            <p class="text-sm text-slate-300">{{ __('Réservation rapide') }}</p>
            <h3 class="mt-1 text-xl font-bold">{{ __('Même service que la dernière fois') }}</h3>

            @if($dernierRendezVous)
            <div class="mt-4 space-y-2 text-sm text-slate-200">
                <p><span class="font-semibold text-white">Service :</span> {{ $dernierRendezVous->service_display_name }}</p>
                <p><span class="font-semibold text-white">Adresse :</span> {{ $dernierRendezVous->adresse ?? '—' }}, {{ $dernierRendezVous->ville ?? '—' }}</p>
                <p><span class="font-semibold text-white">Type :</span> {{ ucfirst($dernierRendezVous->type_lieu ?? '—') }}</p>
                <p><span class="font-semibold text-white">{{ __('Fréquence :') }}</span> {{ ucfirst(str_replace('_', ' ', $dernierRendezVous->frequence ?? '—')) }}</p>
            </div>

            <div class="mt-5">
                <a href="{{ route('client.rendezvous.create', ['prefill' => 'last']) }}" class="inline-flex items-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-slate-100">
                    🔁 Reprendre une réservation similaire
                </a>
            </div>
            @else
            <p class="mt-4 text-sm text-slate-300">
                Votre dernière prestation apparaîtra ici pour faciliter vos prochaines réservations.
            </p>
            @endif
        </div>

        <x-app-card padding="p-6" title="Employés favoris" subtitle="Retrouvez vos favoris et réservez plus vite avec eux.">
            <div class="flex items-center justify-between gap-3">
                @if($isPremium)
                <span class="cu-chip !border-amber-200 !bg-amber-50 !text-amber-700">Premium</span>
                @endif
            </div>

            @if($isPremium)
            <div class="mt-4 space-y-3">
                @forelse($favoriteEmployes as $employe)
                <div class="cu-list-item flex items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-800">{{ $employe->name }}</p>
                        <p class="text-sm text-slate-500">Employé favori</p>
                    </div>

                    <a href="{{ route('client.rendezvous.create', ['employe' => $employe->id]) }}" class="text-sm font-semibold text-sky-600 transition hover:text-sky-700">
                        Réserver
                    </a>
                </div>
                @empty
                <x-empty-state title="Aucun employé favori" message="Ajoutez vos employés préférés pour accélérer vos prochaines réservations premium." icon="★" />
                @endforelse
            </div>
            @else
            <x-empty-state title="Disponible avec l’offre Premium" message="En Premium, vous pouvez sélectionner vos employés favoris et réserver plus facilement avec eux." icon="⭐" />
            @endif
        </x-app-card>
    </div>

    <x-app-card padding="p-6" wire:loading.class="opacity-60">
        <x-section-header title="Mes prochaines interventions" subtitle="Retrouvez vos prochains services planifiés." action-label="Voir tous mes rendez-vous" :action-href="route('client.rendezvous.index')" />

        <div class="mt-5 space-y-4">
            @forelse($avenir as $rdv)
            <div class="cu-list-item">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-lg font-semibold text-slate-900">
                            {{ $rdv->service_display_name }}
                        </p>
                        <p class="mt-1 text-sm text-slate-600">📅 {{ $rdv->date }} à {{ substr((string) $rdv->heure, 0, 5) }}</p>
                        <p class="text-sm text-slate-600">📍 {{ $rdv->adresse ?? 'Adresse non précisée' }}, {{ $rdv->ville ?? '—' }}</p>
                        <p class="text-sm text-slate-600">🧑‍💼 {{ $rdv->employe->name ?? 'Employé à confirmer' }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge :status="$rdv->status" />
                        <x-priority-badge :priority="$rdv->priorite" />
                    </div>
                </div>
            </div>
            @empty
            <x-empty-state title="Aucune intervention à venir" message="Dès qu’un prochain rendez-vous sera planifié, il apparaîtra ici." icon="🧹" />
            @endforelse
        </div>

        @if(method_exists($avenir, 'links'))
        <div class="mt-6">
            {{ $avenir->links() }}
        </div>
        @endif
    </x-app-card>
</div>